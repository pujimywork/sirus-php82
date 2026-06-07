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

    /** Daftar dr_id yang sedang di-expand (tarif visit & konsul per kelas). */
    public array $expanded = [];

    /** Cache tarif per dr_id: ['dr_id' => [['class_id', 'class_desc', 'visit_price', ...], ...]] */
    public array $tarifCache = [];

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

    public function openTarif(string $drId, string $drName): void
    {
        $this->dispatch('master.dokter.openTarif', drId: $drId, drName: $drName);
    }

    /* ===============================
     | Expand row — tarif visit & konsul per kelas
     =============================== */
    public function toggleExpand(string $drId): void
    {
        if (in_array($drId, $this->expanded, true)) {
            $this->expanded = array_values(array_diff($this->expanded, [$drId]));
            return;
        }

        $this->expanded[] = $drId;
        $this->loadTarif($drId);
    }

    private function loadTarif(string $drId): void
    {
        $this->tarifCache[$drId] = DB::table('rsmst_docvisits as dv')
            ->leftJoin('rsmst_class as c', 'c.class_id', '=', 'dv.class_id')
            ->where('dv.dr_id', $drId)
            ->select('dv.class_id', 'c.class_desc', 'dv.visit_price', 'dv.visit_price_bpjs', 'dv.konsul_price', 'dv.konsul_price_bpjs')
            ->orderBy('dv.class_id')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();
    }

    #[On('master.dokter.tarif-saved')]
    public function refreshTarifCache(): void
    {
        // Reload tarif baris yang sedang ter-expand supaya hasil simpan modal langsung tampil.
        foreach ($this->expanded as $drId) {
            $this->loadTarif($drId);
        }
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
    <x-page-title
        title="Master Dokter"
        subtitle="Kelola data dokter untuk aplikasi" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

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
                class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- Scroll area — pola tampilan padat mirip master-pasien:
                     setiap cell multi-baris dengan label kecil di subtitle. --}}
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-center">
                                <th class="px-3 py-2 w-8"></th>
                                <th class="px-3 py-2 font-semibold">DOKTER &amp; POLI</th>
                                <th class="px-3 py-2 font-semibold">KONTAK</th>
                                <th class="px-3 py-2 font-semibold">TARIF &amp; ADMIN</th>
                                <th class="px-3 py-2 font-semibold">STATUS</th>
                                <th class="px-3 py-2 font-semibold">AKSI</th>
                            </tr>
                        </thead>

                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->rows as $row)
                                @php $isExpanded = in_array($row->dr_id, $expanded, true); @endphp
                                <tr wire:key="dokter-row-{{ $row->dr_id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">

                                    {{-- Chevron expand tarif per kelas --}}
                                    <td class="px-2 py-2 text-center align-top">
                                        <button type="button" wire:click="toggleExpand('{{ $row->dr_id }}')"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-700 transition"
                                            title="Lihat tarif visit & konsul per kelas">
                                            <svg class="w-4 h-4 transition-transform {{ $isExpanded ? 'rotate-90' : '' }}"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>
                                    </td>

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

                                        {{-- Tarif Umum + BPJS sebelahan (irit tempat) --}}
                                        <div class="mt-2 pt-1.5 border-t border-gray-100 dark:border-gray-800">
                                            <div class="grid grid-cols-2 gap-x-4">
                                                {{-- Kolom kiri: Umum --}}
                                                <div>
                                                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300 text-left mb-0.5">Umum</div>
                                                    <div class="grid grid-cols-[auto_1fr] gap-x-2 gap-y-0.5 text-sm">
                                                        <span class="text-gray-600 dark:text-gray-300 text-left">Poli</span>
                                                        <span class="font-mono text-gray-900 dark:text-gray-100">Rp {{ number_format((float) $row->poli_price) }}</span>

                                                        <span class="text-gray-600 dark:text-gray-300 text-left">UGD</span>
                                                        <span class="font-mono text-gray-900 dark:text-gray-100">Rp {{ number_format((float) $row->ugd_price) }}</span>
                                                    </div>
                                                </div>

                                                {{-- Kolom kanan: BPJS --}}
                                                <div class="border-l border-gray-100 dark:border-gray-800 pl-3">
                                                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300 text-left mb-0.5">BPJS</div>
                                                    <div class="grid grid-cols-[auto_1fr] gap-x-2 gap-y-0.5 text-sm">
                                                        <span class="text-gray-600 dark:text-gray-300 text-left">Poli</span>
                                                        <span class="font-mono text-gray-900 dark:text-gray-100">Rp {{ number_format((float) $row->poli_price_bpjs) }}</span>

                                                        <span class="text-gray-600 dark:text-gray-300 text-left">UGD</span>
                                                        <span class="font-mono text-gray-900 dark:text-gray-100">Rp {{ number_format((float) $row->ugd_price_bpjs) }}</span>
                                                    </div>
                                                </div>
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

                                            <x-outline-button type="button"
                                                wire:click="openTarif('{{ $row->dr_id }}', '{{ addslashes($row->dr_name) }}')"
                                                class="px-2 py-1 text-xs">
                                                Tarif Visit &amp; Konsul
                                            </x-outline-button>

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

                                {{-- Expand row: tarif visit & konsul per kelas (rsmst_docvisits) --}}
                                @if ($isExpanded)
                                    @php $tarifKelas = $tarifCache[$row->dr_id] ?? []; @endphp
                                    <tr wire:key="dokter-tarif-{{ $row->dr_id }}">
                                        <td colspan="6" class="px-6 py-4 bg-gray-50/60 dark:bg-gray-800/30">
                                            <div
                                                class="bg-white border border-gray-200 dark:border-gray-700 dark:bg-gray-900 rounded-xl overflow-hidden lg:max-w-3xl">
                                                <div
                                                    class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 bg-emerald-50/50 dark:bg-emerald-900/10">
                                                    <h4
                                                        class="text-xs font-semibold text-emerald-700 dark:text-emerald-300 uppercase tracking-wider">
                                                        Tarif Visit &amp; Konsul per Kelas
                                                    </h4>
                                                    <div class="flex items-center gap-2">
                                                        <x-badge variant="gray">{{ count($tarifKelas) }} kelas</x-badge>
                                                        <x-outline-button type="button"
                                                            wire:click="openTarif('{{ $row->dr_id }}', '{{ addslashes($row->dr_name) }}')"
                                                            class="px-2 py-1 text-xs">
                                                            Kelola Tarif
                                                        </x-outline-button>
                                                    </div>
                                                </div>
                                                <table class="w-full text-xs">
                                                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                                                        <tr class="text-xs text-gray-500 uppercase">
                                                            <th class="px-3 py-2 text-left font-medium" rowspan="2">Kelas</th>
                                                            <th class="px-3 py-1.5 text-center font-medium border-l border-gray-200 dark:border-gray-700"
                                                                colspan="2">Visit</th>
                                                            <th class="px-3 py-1.5 text-center font-medium border-l border-gray-200 dark:border-gray-700"
                                                                colspan="2">Konsul</th>
                                                        </tr>
                                                        <tr class="text-xs text-gray-500 uppercase">
                                                            <th class="px-3 py-1.5 text-right font-medium border-l border-gray-200 dark:border-gray-700">Umum</th>
                                                            <th class="px-3 py-1.5 text-right font-medium">BPJS</th>
                                                            <th class="px-3 py-1.5 text-right font-medium border-l border-gray-200 dark:border-gray-700">Umum</th>
                                                            <th class="px-3 py-1.5 text-right font-medium">BPJS</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                        @forelse ($tarifKelas as $kls)
                                                            <tr wire:key="dokter-tarif-{{ $row->dr_id }}-{{ $kls['class_id'] ?? $loop->index }}">
                                                                <td class="px-3 py-2">
                                                                    {{ $kls['class_desc'] ?? 'Kelas ' . $kls['class_id'] }}
                                                                </td>
                                                                <td class="px-3 py-2 text-right font-mono border-l border-gray-100 dark:border-gray-800">
                                                                    Rp {{ number_format((float) ($kls['visit_price'] ?? 0)) }}
                                                                </td>
                                                                <td class="px-3 py-2 text-right font-mono">
                                                                    Rp {{ number_format((float) ($kls['visit_price_bpjs'] ?? 0)) }}
                                                                </td>
                                                                <td class="px-3 py-2 text-right font-mono border-l border-gray-100 dark:border-gray-800">
                                                                    Rp {{ number_format((float) ($kls['konsul_price'] ?? 0)) }}
                                                                </td>
                                                                <td class="px-3 py-2 text-right font-mono">
                                                                    Rp {{ number_format((float) ($kls['konsul_price_bpjs'] ?? 0)) }}
                                                                </td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="5"
                                                                    class="px-3 py-3 text-center text-gray-400 italic">
                                                                    Belum ada tarif per kelas — klik Kelola Tarif untuk mengisi.
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
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

            {{-- Modal tarif Visit & Konsul per kelas --}}
            <livewire:pages::master.master-dokter.master-dokter-tarif-visit-konsul-actions
                wire:key="master-dokter-tarif-visit-konsul-actions" />

        </div>
    </div>
</div>
