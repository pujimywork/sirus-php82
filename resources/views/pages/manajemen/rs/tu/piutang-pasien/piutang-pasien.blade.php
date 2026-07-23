<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use App\Http\Traits\Manajemen\Rs\Tu\PiutangPasienTrait;

new class extends Component {
    use WithPagination;
    use PiutangPasienTrait;

    public string $searchKeyword = '';
    public string $filterJalur = '';   // '' | RJ | UGD | RI
    public string $filterKlaim = '';    // '' | BPJS | UMUM | KRONIS | DOKEL
    public int $itemsPerPage = 10;

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedFilterJalur(): void   { $this->resetPage(); }
    public function updatedFilterKlaim(): void   { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterJalur', 'filterKlaim']);
        $this->resetPage();
    }

    /**
     * Buka rincian administrasi transaksi secara READ-ONLY (meniru Casemix):
     * pakai ulang komponen administrasi tiap jalur, dipaksa read-only via readOnly:true.
     */
    public function openAdministrasi(string $jalur, int $noTransaksi): void
    {
        match ($jalur) {
            'RJ'  => $this->dispatch('emr-rj.administrasi.open', rjNo: $noTransaksi, readOnly: true),
            'UGD' => $this->dispatch('emr-ugd.administrasi.open', rjNo: $noTransaksi, readOnly: true),
            'RI'  => $this->dispatch('emr-ri.administrasi.open', riHdrNo: $noTransaksi, readOnly: true),
            default => null,
        };
    }

    #[Computed]
    public function rows()
    {
        $page    = Paginator::resolveCurrentPage();
        $perPage = $this->itemsPerPage;

        $barisHalaman = $this->piutangPageItems(
            null, null,
            $this->filterJalur, $this->filterKlaim, trim($this->searchKeyword),
            $perPage, $page,
        );

        // Dokter RI = DPJP Utama dari leveling dokter (JSON) — hanya baris halaman ini.
        $this->isiDokterRiLeveling($barisHalaman);

        // Total dari summary (di-cache) → paginasi bernomor tanpa COUNT ekstra.
        return new LengthAwarePaginator(
            $barisHalaman, $this->summary['jumlah'], $perPage, $page, ['path' => request()->url()],
        );
    }

    #[Computed]
    public function summary(): array
    {
        return $this->piutangSummary(
            null, null,
            $this->filterJalur, $this->filterKlaim, trim($this->searchKeyword),
        );
    }
}; ?>

<div>
    <x-page-title
        title="Piutang Pasien"
        subtitle="Pasien dengan transaksi belum lunas (cicilan/bon) — RJ, UGD & RI" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR (standar daftar-rj) --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    {{-- SEARCH --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                                placeholder="Cari No Transaksi / No RM / Nama Pasien..." />
                        </div>
                    </div>

                    {{-- FILTER JALUR --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Jalur" />
                        <x-select-input wire:model.live="filterJalur" class="w-full mt-1 sm:w-36">
                            <option value="">Semua</option>
                            <option value="RJ">Rawat Jalan</option>
                            <option value="UGD">UGD</option>
                            <option value="RI">Rawat Inap</option>
                        </x-select-input>
                    </div>

                    {{-- FILTER KLAIM --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Klaim" />
                        <x-select-input wire:model.live="filterKlaim" class="w-full mt-1 sm:w-32">
                            <option value="">Semua</option>
                            <option value="BPJS">BPJS</option>
                            <option value="UMUM">UMUM</option>
                            <option value="KRONIS">KRONIS</option>
                            <option value="DOKEL">DOKEL</option>
                        </x-select-input>
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center gap-2 ml-auto">
                        <x-toolbar-refresh-reset :label="null" />

                        <div class="w-20">
                            <x-select-input wire:model.live="itemsPerPage" class="text-sm" title="Per halaman">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                    </div>
                </div>
            </div>

            {{-- RINGKASAN (buka/tutup — pola laporan-task-id-rj; default TUTUP) --}}
            @php $sum = $this->summary; @endphp
            <div class="mt-4 bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900"
                x-data="{ open: false }">

                {{-- Header toggle: borderless row di dalam frame --}}
                <button type="button" @click="open = !open"
                    class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors rounded-2xl
                           hover:bg-surface-soft dark:hover:bg-gray-800
                           focus:outline-none focus:ring-1 focus:ring-gray-300">

                    {{-- Title + summary --}}
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold text-body dark:text-gray-200">
                            Ringkasan Piutang
                        </div>
                        <div class="text-xs text-muted dark:text-gray-400">
                            Total Rp {{ number_format($sum['sisa'], 0, ',', '.') }}
                            · {{ number_format($sum['jumlah'], 0, ',', '.') }} transaksi
                        </div>
                    </div>

                    {{-- CTA + chevron --}}
                    <span class="hidden text-xs sm:inline text-muted dark:text-gray-400">
                        <span x-text="open ? 'Sembunyikan' : 'Lihat detail'"></span>
                    </span>
                    <svg class="w-4 h-4 transition-transform duration-200 text-muted-soft shrink-0"
                        :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                {{-- Body (collapsible) — divider top --}}
                <div x-cloak x-show="open"
                    class="px-4 pb-4 border-t border-hairline dark:border-gray-700"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0">

                    <div class="grid grid-cols-2 gap-3 mt-3 sm:grid-cols-3 lg:grid-cols-5">
                        <div class="p-3 border rounded-xl bg-rose-50 border-rose-200 dark:bg-rose-900/20 dark:border-rose-800">
                            <div class="text-xs uppercase text-rose-700 dark:text-rose-300">Total Piutang</div>
                            <div class="mt-1 text-xl font-bold text-rose-800 dark:text-rose-200">Rp {{ number_format($sum['sisa'], 0, ',', '.') }}</div>
                        </div>
                        <div class="p-3 bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                            <div class="text-xs uppercase text-muted">Jumlah Transaksi</div>
                            <div class="mt-1 text-xl font-bold text-ink dark:text-gray-100">{{ number_format($sum['jumlah'], 0, ',', '.') }}</div>
                        </div>
                        <div class="p-3 bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                            <div class="text-xs uppercase text-muted">Piutang RJ</div>
                            <div class="mt-1 text-lg font-bold text-ink dark:text-gray-100">Rp {{ number_format($sum['rj'], 0, ',', '.') }}</div>
                        </div>
                        <div class="p-3 bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                            <div class="text-xs uppercase text-muted">Piutang UGD</div>
                            <div class="mt-1 text-lg font-bold text-ink dark:text-gray-100">Rp {{ number_format($sum['ugd'], 0, ',', '.') }}</div>
                        </div>
                        <div class="p-3 bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                            <div class="text-xs uppercase text-muted">Piutang RI</div>
                            <div class="mt-1 text-lg font-bold text-ink dark:text-gray-100">Rp {{ number_format($sum['ri'], 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- CARD TABEL (model pelayanan-rj: baris-kartu + paginasi bernomor) --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="w-full min-w-full text-sm -mt-2 border-separate border-spacing-y-2">
                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-5 py-3">Pasien</th>
                                <th class="px-4 py-3">Kunjungan</th>
                                <th class="px-4 py-3">Rincian Tagihan</th>
                                <th class="px-5 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="piutang-row-{{ $row->jalur }}-{{ $row->no_transaksi }}"
                                    class="transition rounded-2xl shadow-sm ring-1 ring-hairline bg-canvas hover:shadow-md hover:bg-surface-soft dark:bg-gray-900 dark:ring-gray-700 dark:hover:bg-gray-800">
                                    <td class="px-5 py-4 align-top rounded-l-2xl">
                                        <x-list.identitas-pasien
                                            :regNo="$row->reg_no"
                                            :nama="$row->reg_name"
                                            :sex="$row->sex"
                                            :tglLahir="$row->birth_date"
                                            :alamat="$row->address" />
                                    </td>
                                    <td class="px-4 py-4 space-y-0.5 align-top">
                                        @php
                                            $jalurLabel = ['RJ' => 'Rawat Jalan', 'UGD' => 'UGD', 'RI' => 'Rawat Inap'][$row->jalur] ?? $row->jalur;
                                            $jalurVariant = ['RJ' => 'info', 'UGD' => 'danger', 'RI' => 'purple'][$row->jalur] ?? 'gray';
                                        @endphp
                                        {{-- Jalur --}}
                                        <x-badge :variant="$jalurVariant" class="whitespace-nowrap">{{ $jalurLabel }}</x-badge>

                                        {{-- Unit/Poli — hanya RJ (UGD/RI tak ada sub-unit) --}}
                                        @if (filled($row->unit))
                                            <div class="font-semibold leading-tight text-brand dark:text-emerald-400">{{ $row->unit }}</div>
                                        @endif

                                        {{-- Dokter (RI = DPJP Utama) --}}
                                        <div class="text-sm leading-tight text-muted dark:text-gray-400">{{ $row->dokter }}</div>

                                        {{-- Klaim --}}
                                        <div class="mt-0.5">
                                            <x-list.klaim-badge :status="$row->klaim_status" :desc="$row->klaim_desc" :id="$row->klaim_id" />
                                        </div>

                                        {{-- Tanggal — RI: Masuk & Keluar; RJ/UGD: satu tanggal --}}
                                        @if (filled($row->tgl_masuk))
                                            <div class="text-xs leading-tight text-muted dark:text-gray-500">Masuk: {{ $row->tgl_masuk }}</div>
                                            <div class="text-xs leading-tight text-muted dark:text-gray-500">Keluar: {{ $row->tgl }}</div>
                                        @else
                                            <div class="text-xs leading-tight text-muted dark:text-gray-500">{{ $row->tgl }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top whitespace-nowrap">
                                        <div class="space-y-1 text-sm min-w-[12rem]">
                                            <div class="flex justify-between gap-4">
                                                <span class="text-muted dark:text-gray-400">Total</span>
                                                <span class="font-medium text-body dark:text-gray-300">{{ number_format($row->total, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="flex justify-between gap-4">
                                                <span class="text-muted dark:text-gray-400">Diskon</span>
                                                <span class="text-muted dark:text-gray-400">{{ number_format($row->diskon, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="flex justify-between gap-4">
                                                <span class="text-muted dark:text-gray-400">Dibayar</span>
                                                <span class="text-body dark:text-gray-300">{{ number_format($row->bayar, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="flex justify-between gap-4 pt-1.5 mt-1.5 border-t border-hairline dark:border-gray-700">
                                                <span class="font-semibold text-rose-700 dark:text-rose-300">Sisa Piutang</span>
                                                <span class="font-bold text-rose-700 dark:text-rose-300">Rp {{ number_format($row->sisa, 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-center align-top rounded-r-2xl">
                                        <x-secondary-button type="button"
                                            wire:click="openAdministrasi('{{ $row->jalur }}', {{ (int) $row->no_transaksi }})"
                                            class="whitespace-nowrap" title="Lihat rincian administrasi (read-only)">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            Detail
                                        </x-secondary-button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-16 text-center text-muted dark:text-gray-400">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg class="w-10 h-10 text-hairline" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>Tidak ada piutang pasien untuk filter ini.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION (bernomor) --}}
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

        </div>
    </div>

    {{-- Modal Administrasi READ-ONLY per jalur (pola Casemix) — di-embed sekali, --}}
    {{-- masing-masing hanya bereaksi pada event-nya sendiri (readOnly:true). --}}
    <livewire:pages::transaksi.rj.administrasi-rj.administrasi-rj wire:key="piutang-administrasi-rj" />
    <livewire:pages::transaksi.ugd.administrasi-ugd.administrasi-ugd wire:key="piutang-administrasi-ugd" />
    <livewire:pages::transaksi.ri.administrasi-ri.administrasi-ri wire:key="piutang-administrasi-ri" />
</div>
