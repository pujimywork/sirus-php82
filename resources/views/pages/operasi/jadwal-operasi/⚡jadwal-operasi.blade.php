<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    /* ─── Filter & Pagination ─── */
    public string $searchKeyword = '';
    public string $filterStatus  = '';
    public int    $itemsPerPage  = 10;

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void  { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }

    /* ─── Child triggers ─── */
    public function openCreate(): void
    {
        $this->dispatch('jadwal-operasi.openCreate');
    }

    public function openEdit(string $noRawat): void
    {
        $this->dispatch('jadwal-operasi.openEdit', noRawat: $noRawat);
    }

    public function requestDelete(string $noRawat): void
    {
        $this->dispatch('jadwal-operasi.requestDelete', noRawat: $noRawat);
    }

    #[On('jadwal-operasi.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    /* ─── Query ─── */
    #[Computed]
    public function rows()
    {
        $kw = trim($this->searchKeyword);

        $q = DB::table('booking_operasi')
            ->leftJoin('rsmst_doctors', 'booking_operasi.dr_id', '=', 'rsmst_doctors.dr_id')
            ->leftJoin('rsmst_polis', 'booking_operasi.poli_id', '=', 'rsmst_polis.poli_id')
            ->select('booking_operasi.*', 'rsmst_doctors.dr_name', 'rsmst_polis.poli_desc')
            ->orderBy('booking_operasi.no_rawat', 'desc');

        if ($kw !== '') {
            $up = mb_strtoupper($kw);
            $q->where(function ($sub) use ($up) {
                $sub->orWhereRaw('UPPER(no_rawat) LIKE ?',   ["%{$up}%"])
                    ->orWhereRaw('UPPER(reg_no) LIKE ?',     ["%{$up}%"])
                    ->orWhereRaw('UPPER(nm_paket) LIKE ?',   ["%{$up}%"])
                    ->orWhereRaw('UPPER(kode_paket) LIKE ?', ["%{$up}%"]);
            });
        }

        if ($this->filterStatus !== '') {
            $q->where('status', $this->filterStatus);
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Jadwal Operasi
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Kelola data booking & jadwal operasi pasien
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    {{-- SEARCH + FILTER --}}
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <div class="w-full lg:max-w-xl">
                            <x-input-label for="searchKeyword" value="Cari" class="sr-only" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="No. Rawat, Reg, Paket..." class="block w-full" />
                        </div>
                        <div class="w-full sm:w-56">
                            <x-input-label for="filterStatus" value="Status" class="sr-only" />
                            <x-select-input id="filterStatus" wire:model.live="filterStatus" class="w-full">
                                <option value="">Semua Status</option>
                                <option value="Menunggu">Menunggu</option>
                                <option value="Selesai">Selesai</option>
                            </x-select-input>
                        </div>
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Jadwal Operasi Baru
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-340px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-semibold whitespace-nowrap">No. Rawat</th>
                                <th class="px-4 py-3 font-semibold whitespace-nowrap">Reg No</th>
                                <th class="px-4 py-3 font-semibold whitespace-nowrap">Tanggal / Jam</th>
                                <th class="px-4 py-3 font-semibold whitespace-nowrap">Paket / Ruang</th>
                                <th class="px-4 py-3 font-semibold whitespace-nowrap">Dokter / Poli</th>
                                <th class="px-4 py-3 font-semibold whitespace-nowrap">Status</th>
                                <th class="px-4 py-3 font-semibold whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($this->rows as $row)
                                <tr wire:key="ok-row-{{ $row->no_rawat }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-3 font-mono text-xs whitespace-nowrap">
                                        {{ $row->no_rawat }}
                                    </td>
                                    <td class="px-4 py-3 font-semibold whitespace-nowrap">
                                        {{ $row->reg_no }}
                                        @if($row->no_peserta)
                                            <div class="text-[11px] font-normal text-gray-400">{{ $row->no_peserta }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div>{{ $row->tanggal ? \Carbon\Carbon::parse($row->tanggal)->format('d/m/Y') : '-' }}</div>
                                        <div class="text-[11px] text-gray-400">{{ substr($row->jam_mulai, 0, 5) }} – {{ substr($row->jam_selesai, 0, 5) }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold">{{ $row->nm_paket ?: $row->kode_paket ?: '-' }}</div>
                                        @if($row->kd_ruang_ok)
                                            <div class="text-[11px] text-gray-400">Ruang: {{ $row->kd_ruang_ok }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div>{{ $row->dr_name ?: $row->dr_id ?: '-' }}</div>
                                        <div class="text-[11px] text-gray-400">{{ $row->poli_desc ?: $row->poli_id ?: '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @php
                                            $statusVariant = match($row->status) {
                                                'Selesai'  => 'success',
                                                'Menunggu' => 'warning',
                                                default    => 'gray',
                                            };
                                        @endphp
                                        <x-badge :variant="$statusVariant">{{ $row->status ?: '-' }}</x-badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit('{{ $row->no_rawat }}')" class="px-2 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>
                                            <x-confirm-button
                                                variant="danger"
                                                :action="'requestDelete(\'' . $row->no_rawat . '\')'"
                                                title="Hapus Jadwal Operasi"
                                                message="Yakin hapus jadwal {{ $row->reg_no }} – {{ $row->nm_paket }}?"
                                                confirmText="Ya, hapus"
                                                cancelText="Batal"
                                                class="px-2 py-1 text-xs">
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data jadwal operasi belum ada.
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

            {{-- Child actions --}}
            <livewire:pages::operasi.jadwal-operasi.jadwal-operasi-actions wire:key="jadwal-operasi-actions" />

        </div>
    </div>
</div>
