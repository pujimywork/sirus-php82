<?php
// resources/views/pages/master/master-dokter/master-dokter.blade.php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    /* ===============================
     | Filter & Pagination
     =============================== */
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

    /* ===============================
     | Child modal triggers
     =============================== */
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

    /* ===============================
     | Toggle Aktif / Non-aktif langsung dari table
     =============================== */
    public function toggleActive(string $drId): void
    {
        $current = (string) DB::table('rsmst_doctors')->where('dr_id', $drId)->value('active_status');
        $newValue = $current === '1' ? '0' : '1';

        DB::table('rsmst_doctors')
            ->where('dr_id', $drId)
            ->update(['active_status' => $newValue]);

        // Bust computed cache supaya table langsung refresh dengan nilai baru.
        unset($this->rows);

        $this->dispatch(
            'toast',
            type: 'success',
            message: $newValue === '1' ? 'Dokter diaktifkan.' : 'Dokter dinon-aktifkan.',
        );
    }

    /* ===============================
     | Refresh setelah child save
     =============================== */
    #[On('master.dokter.saved')]
    public function refreshAfterSaved(): void
    {
        // Bust computed cache + reset pagination supaya data terbaru tampil
        // (bukan cuma resetPage — kalau page sudah 1, computed tidak otomatis re-eval).
        unset($this->rows);
        $this->resetPage();
    }

    /* ===============================
     | Computed queries
     =============================== */
    #[Computed]
    public function baseQuery()
    {
        $searchKeyword = trim($this->searchKeyword);

        $queryBuilder = DB::table('rsmst_doctors as a')
            ->join('rsmst_polis as b', 'a.poli_id', '=', 'b.poli_id')
            ->select(
                'a.dr_id', 'a.dr_name', 'a.dr_phone', 'a.dr_address',
                'a.kd_dr_bpjs', 'a.dr_uuid', 'a.dr_nik',
                'a.basic_salary', 'a.rs_admin',
                'a.poli_price', 'a.ugd_price',
                'a.poli_price_bpjs', 'a.ugd_price_bpjs',
                'a.active_status',
                'a.poli_id', 'b.poli_desc', 'b.kd_poli_bpjs',
            )
            // Aktif (active_status='1') di atas → grouping per poli → alfabetik nama.
            // Strict check '1' konsisten dengan logic toggle/badge.
            ->orderByRaw("CASE WHEN a.active_status = '1' THEN 0 ELSE 1 END")
            ->orderBy('b.poli_desc', 'asc')
            ->orderBy('a.dr_name', 'asc');

        if ($searchKeyword !== '') {
            $upper = mb_strtoupper($searchKeyword);

            $queryBuilder->where(function ($q) use ($upper, $searchKeyword) {
                if (ctype_digit($searchKeyword)) {
                    $q->orWhere('a.dr_id', $searchKeyword);
                }

                $q->orWhereRaw('UPPER(a.dr_name) LIKE ?', ["%{$upper}%"])
                    ->orWhereRaw('UPPER(a.dr_phone) LIKE ?', ["%{$upper}%"])
                    ->orWhereRaw('UPPER(a.poli_id) LIKE ?', ["%{$upper}%"])
                    ->orWhereRaw('UPPER(b.poli_desc) LIKE ?', ["%{$upper}%"]);
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
                Master Dokter
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Kelola data dokter untuk aplikasi
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TOOLBAR: sticky, search + per-page + tambah --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Dokter" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari nama / ID / poli / telepon..." class="block w-full" />
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="7">7</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>

                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Data Dokter Baru
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- TABLE CARD --}}
            <div
                class="mt-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- Scroll area — pola tampilan padat mirip master-pasien:
                     setiap cell multi-baris dengan label kecil di subtitle. --}}
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-center">
                                <th class="px-3 py-2 font-semibold">DOKTER &amp; POLI</th>
                                <th class="px-3 py-2 font-semibold">KONTAK</th>
                                <th class="px-3 py-2 font-semibold">TARIF &amp; ADMIN</th>
                                <th class="px-3 py-2 font-semibold">STATUS</th>
                                <th class="px-3 py-2 font-semibold">AKSI</th>
                            </tr>
                        </thead>

                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->rows as $row)
                                <tr wire:key="dokter-row-{{ $row->dr_id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">

                                    {{-- DOKTER & POLI: setiap data ada label-nya biar jelas --}}
                                    <td class="px-3 py-2 align-top">
                                        <div class="text-sm text-gray-600 dark:text-gray-300">ID Dokter</div>
                                        <div class="font-mono font-bold text-brand dark:text-brand-lime whitespace-nowrap">{{ $row->dr_id }}</div>

                                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">Nama Dokter</div>
                                        <div class="font-bold text-gray-900 dark:text-white">{{ $row->dr_name }}</div>

                                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">Poli</div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ $row->poli_desc }}
                                            @if (!empty($row->kd_poli_bpjs))
                                                <span class="ml-1 text-sm font-normal text-gray-600 dark:text-gray-400">(Kode BPJS Poli: <span class="font-mono text-gray-900 dark:text-gray-100">{{ $row->kd_poli_bpjs }}</span>)</span>
                                            @endif
                                        </div>

                                        @if (!empty($row->dr_nik) || !empty($row->kd_dr_bpjs) || !empty($row->dr_uuid))
                                            <div class="mt-2 pt-1.5 border-t border-gray-200 dark:border-gray-700 space-y-1 text-sm">
                                                @if (!empty($row->dr_nik))
                                                    <div>
                                                        <span class="text-gray-600 dark:text-gray-300">NIK Dokter:</span>
                                                        <span class="ml-1 font-mono text-gray-900 dark:text-gray-100">{{ $row->dr_nik }}</span>
                                                    </div>
                                                @endif
                                                @if (!empty($row->kd_dr_bpjs))
                                                    <div>
                                                        <span class="text-gray-600 dark:text-gray-300">Kode Dokter BPJS:</span>
                                                        <span class="ml-1 font-mono text-gray-900 dark:text-gray-100">{{ $row->kd_dr_bpjs }}</span>
                                                    </div>
                                                @endif
                                                @if (!empty($row->dr_uuid))
                                                    <div>
                                                        <span class="text-gray-600 dark:text-gray-300">UUID Satusehat:</span>
                                                        <span class="ml-1 font-mono text-gray-900 dark:text-gray-100">{{ $row->dr_uuid }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </td>

                                    {{-- KONTAK: telepon + alamat dengan label --}}
                                    <td class="px-3 py-2 align-top">
                                        <div class="text-sm text-gray-600 dark:text-gray-300">Telepon</div>
                                        <div class="font-mono text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $row->dr_phone ?? '-' }}</div>

                                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">Alamat</div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100 max-w-xs truncate" title="{{ $row->dr_address }}">
                                            {{ $row->dr_address ?? '-' }}
                                        </div>
                                    </td>

                                    {{-- TARIF & ADMIN: kelompokkan biar jelas (Umum vs BPJS) --}}
                                    <td class="px-3 py-2 align-top text-right whitespace-nowrap">
                                        {{-- Gaji & Administrasi RS --}}
                                        <div class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-sm">
                                            <span class="text-gray-600 dark:text-gray-300 text-left">Gaji Pokok</span>
                                            <span class="font-mono text-gray-900 dark:text-gray-100">Rp {{ number_format((float) $row->basic_salary) }}</span>

                                            <span class="text-gray-600 dark:text-gray-300 text-left">Admin RS</span>
                                            <span class="font-mono text-gray-900 dark:text-gray-100">Rp {{ number_format((float) $row->rs_admin) }}</span>
                                        </div>

                                        {{-- Tarif Umum (Pasien Umum / Bayar Sendiri) --}}
                                        <div class="mt-2 pt-1.5 border-t border-gray-100 dark:border-gray-800">
                                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300 text-left mb-0.5">Tarif Umum</div>
                                            <div class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-sm">
                                                <span class="text-gray-600 dark:text-gray-300 text-left">Tarif Poli</span>
                                                <span class="font-mono text-gray-900 dark:text-gray-100">Rp {{ number_format((float) $row->poli_price) }}</span>

                                                <span class="text-gray-600 dark:text-gray-300 text-left">Tarif UGD</span>
                                                <span class="font-mono text-gray-900 dark:text-gray-100">Rp {{ number_format((float) $row->ugd_price) }}</span>
                                            </div>
                                        </div>

                                        {{-- Tarif BPJS --}}
                                        <div class="mt-2 pt-1.5 border-t border-gray-100 dark:border-gray-800">
                                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300 text-left mb-0.5">Tarif BPJS</div>
                                            <div class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-sm">
                                                <span class="text-gray-600 dark:text-gray-300 text-left">Tarif Poli BPJS</span>
                                                <span class="font-mono text-gray-900 dark:text-gray-100">Rp {{ number_format((float) $row->poli_price_bpjs) }}</span>

                                                <span class="text-gray-600 dark:text-gray-300 text-left">Tarif UGD BPJS</span>
                                                <span class="font-mono text-gray-900 dark:text-gray-100">Rp {{ number_format((float) $row->ugd_price_bpjs) }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- STATUS: toggle inline (rata tengah) --}}
                                    <td class="px-3 py-2 align-top">
                                        @php $isActive = (string) $row->active_status === '1'; @endphp
                                        <div class="flex justify-center">
                                            <x-toggle wire:key="dokter-toggle-{{ $row->dr_id }}-{{ $isActive ? 1 : 0 }}"
                                                :current="$isActive ? '1' : '0'" trueValue="1" falseValue="0"
                                                wireClick="toggleActive('{{ $row->dr_id }}')">
                                                {{ $isActive ? 'Aktif' : 'Nonaktif' }}
                                            </x-toggle>
                                        </div>
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="px-3 py-2 align-top">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit('{{ $row->dr_id }}')" class="px-2 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>

                                            <x-confirm-button variant="danger"
                                                :action="'requestDelete(\'' . $row->dr_id . '\')'"
                                                title="Hapus Dokter"
                                                :message="'Yakin hapus dokter ' . $row->dr_name . '?'"
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

                {{-- Pagination sticky bawah card --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            {{-- Child actions component --}}
            <livewire:pages::master.master-dokter.master-dokter-actions wire:key="master-dokter-actions" />

        </div>
    </div>
</div>
