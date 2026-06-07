<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int $itemsPerPage = 10;

    /** Daftar pact_id yang sedang di-expand (tampilin paket di expand row). */
    public array $expanded = [];

    /** Cache paket per pact_id: ['pact_id' => ['kelas' => [...], 'others' => [...], 'products' => [...]]] */
    public array $paketCache = [];

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    public function toggleExpand(string $pactId): void
    {
        if (in_array($pactId, $this->expanded, true)) {
            $this->expanded = array_values(array_diff($this->expanded, [$pactId]));
            return;
        }

        $this->expanded[] = $pactId;
        $this->loadPaket($pactId);
    }

    private function loadPaket(string $pactId): void
    {
        $kelas = DB::table('rsmst_actpclasses as ac')
            ->leftJoin('rsmst_class as c', 'c.class_id', '=', 'ac.class_id')
            ->where('ac.pact_id', $pactId)
            ->select('ac.class_id', 'c.class_desc', 'ac.actp_price', 'ac.actp_price_bpjs')
            ->orderBy('ac.class_id')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $others = DB::table('rsmst_actparothers as ap')
            ->leftJoin('rsmst_others as o', 'o.other_id', '=', 'ap.other_id')
            ->where('ap.pact_id', $pactId)
            ->select('ap.other_id', 'o.other_desc', 'ap.acto_price')
            ->orderBy('ap.other_id')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $products = DB::table('rsmst_actparproducts as ap')
            ->leftJoin('immst_products as p', 'p.product_id', '=', 'ap.product_id')
            ->where('ap.pact_id', $pactId)
            ->select('ap.product_id', 'p.product_name', 'ap.actprod_qty', 'p.sales_price')
            ->orderBy('ap.product_id')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $this->paketCache[$pactId] = ['kelas' => $kelas, 'others' => $others, 'products' => $products];
    }

    public function openCreate(): void
    {
        $this->dispatch('master.jasa-medis.openCreate');
    }

    public function openEdit(string $pactId): void
    {
        $this->dispatch('master.jasa-medis.openEdit', pactId: $pactId);
    }

    public function requestDelete(string $pactId): void
    {
        $this->dispatch('master.jasa-medis.requestDelete', pactId: $pactId);
    }

    public function toggleActive(string $pactId): void
    {
        $this->dispatch('master.jasa-medis.toggleActive', pactId: $pactId);
    }

    #[On('master.jasa-medis.saved')]
    public function refreshAfterSaved(): void
    {
        // Invalidate paket cache supaya expand-row reload data terbaru.
        $this->paketCache = [];
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $query = DB::table('rsmst_actparamedics')->select('pact_id', 'pact_desc', 'pact_price', 'pact_price_bpjs', 'active_status');

        $keyword = trim($this->searchKeyword);
        if ($keyword !== '') {
            $upper = mb_strtoupper($keyword);
            $query->where(function ($q) use ($upper) {
                $q->whereRaw('UPPER(pact_id) LIKE ?', ["%{$upper}%"])->orWhereRaw('UPPER(pact_desc) LIKE ?', ["%{$upper}%"]);
            });
        }

        return $query->orderBy('pact_desc')->paginate($this->itemsPerPage);
    }

    public function formatRupiah($price): string
    {
        return 'Rp ' . number_format((int) ($price ?? 0), 0, ',', '.');
    }
};
?>

<div>
    <x-page-title
        title="Master Jasa Medis"
        subtitle="Kelola tarif jasa medis (Umum &amp; BPJS) beserta paket bundling lain-lain dan obat." />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Jasa Medis" class="sr-only" />
                        {{-- TANPA wire:key — key dinamis (now()) bikin input remount tiap render → fokus hilang saat ketik --}}
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari jasa medis (kode / nama)..." class="block w-full" />
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Jasa Medis
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- REKAP --}}
            @php
                $rekap = $this->rows;
                $totalRows = $rekap->total();
                $items = collect($rekap->items());
                $aktif = $items->filter(fn($r) => (string) ($r->active_status ?? '0') === '1')->count();
                $nonAktif = $totalRows - $aktif;
            @endphp
            <div class="flex items-center gap-4 px-5 py-2 border-b border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 text-xs flex-wrap">
                <div class="flex items-center gap-2">
                    <span
                        class="px-1.5 py-0.5 rounded bg-gray-200/70 dark:bg-gray-700/60 font-semibold text-[10px] uppercase tracking-wider text-gray-600 dark:text-gray-300">Jasa
                        Medis</span>
                    <div class="flex items-center gap-1.5">
                        <span class="text-gray-500 dark:text-gray-400">Total</span>
                        <span class="font-bold text-gray-700 dark:text-gray-200">{{ $totalRows }}</span>
                    </div>
                    <span class="text-gray-300 dark:text-gray-600">|</span>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="text-gray-500 dark:text-gray-400">Aktif (di halaman ini)</span>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $aktif }}</span>
                    </div>
                    <span class="text-gray-300 dark:text-gray-600">|</span>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block w-2 h-2 rounded-full bg-red-400"></span>
                        <span class="text-gray-500 dark:text-gray-400">Non-Aktif (di halaman ini)</span>
                        <span class="font-bold text-red-500 dark:text-red-400">{{ $nonAktif }}</span>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-3 py-3 w-8"></th>
                                <th class="px-4 py-3 font-semibold">KODE</th>
                                <th class="px-4 py-3 font-semibold">NAMA</th>
                                <th class="px-4 py-3 font-semibold text-right">TARIF UMUM</th>
                                <th class="px-4 py-3 font-semibold text-right">TARIF BPJS</th>
                                <th class="px-4 py-3 font-semibold">STATUS</th>
                                <th class="px-4 py-3 font-semibold">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse($this->rows as $row)
                                @php $isExpanded = in_array($row->pact_id, $expanded, true); @endphp

                                <tr wire:key="jm-row-{{ $row->pact_id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-2 py-3 text-center">
                                        <button type="button" wire:click="toggleExpand('{{ $row->pact_id }}')"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-700 transition"
                                            title="Lihat paket">
                                            <svg class="w-4 h-4 transition-transform {{ $isExpanded ? 'rotate-90' : '' }}"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ $row->pact_id }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ $row->pact_desc }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-600 dark:text-green-400">
                                        {{ $this->formatRupiah($row->pact_price) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-600 dark:text-cyan-400">
                                        {{ $this->formatRupiah($row->pact_price_bpjs) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-toggle :current="(string) ($row->active_status ?? '0')" trueValue="1" falseValue="0"
                                            wireClick="toggleActive('{{ $row->pact_id }}')">
                                            {{ (string) ($row->active_status ?? '0') === '1' ? 'Aktif' : 'Tidak Aktif' }}
                                        </x-toggle>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit('{{ $row->pact_id }}')" class="px-2 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>
                                            <x-confirm-button variant="danger" :action="'requestDelete(\'' . $row->pact_id . '\')'" title="Hapus Jasa Medis"
                                                message="Yakin hapus {{ $row->pact_desc }}? Paket-nya juga ikut terhapus."
                                                confirmText="Ya, hapus" cancelText="Batal" class="px-2 py-1 text-xs">
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Expand row paket --}}
                                @if ($isExpanded)
                                    @php
                                        $paket = $paketCache[$row->pact_id] ?? ['kelas' => [], 'others' => [], 'products' => []];
                                    @endphp
                                    <tr wire:key="jm-paket-{{ $row->pact_id }}">
                                        <td colspan="7" class="px-6 py-4 bg-gray-50/60 dark:bg-gray-800/30">
                                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                                {{-- Tarif per Kelas --}}
                                                <div
                                                    class="bg-white border border-gray-200 dark:border-gray-700 dark:bg-gray-900 rounded-xl overflow-hidden">
                                                    <div
                                                        class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 bg-emerald-50/50 dark:bg-emerald-900/10">
                                                        <h4
                                                            class="text-xs font-semibold text-emerald-700 dark:text-emerald-300 uppercase tracking-wider">
                                                            Tarif per Kelas
                                                        </h4>
                                                        <x-badge variant="gray">{{ count($paket['kelas'] ?? []) }} kelas</x-badge>
                                                    </div>
                                                    <table class="w-full text-xs">
                                                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                                                            <tr class="text-left text-gray-500 uppercase">
                                                                <th class="px-3 py-2 font-medium">Kelas</th>
                                                                <th class="px-3 py-2 font-medium text-right">Umum</th>
                                                                <th class="px-3 py-2 font-medium text-right">BPJS</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                            @forelse($paket['kelas'] ?? [] as $kls)
                                                                <tr wire:key="paket-kelas-{{ ($kls['class_id'] ?? '') . '-' . $loop->index }}">
                                                                    <td class="px-3 py-2">
                                                                        {{ $kls['class_desc'] ?? 'Kelas ' . $kls['class_id'] }}
                                                                    </td>
                                                                    <td class="px-3 py-2 text-right font-mono">
                                                                        {{ $this->formatRupiah($kls['actp_price'] ?? 0) }}
                                                                    </td>
                                                                    <td class="px-3 py-2 text-right font-mono">
                                                                        {{ $this->formatRupiah($kls['actp_price_bpjs'] ?? 0) }}
                                                                    </td>
                                                                </tr>
                                                            @empty
                                                                <tr>
                                                                    <td colspan="3"
                                                                        class="px-3 py-3 text-center text-gray-400 italic">
                                                                        Belum ada tarif per kelas
                                                                    </td>
                                                                </tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>

                                                {{-- Paket Lain-Lain --}}
                                                <div
                                                    class="bg-white border border-gray-200 dark:border-gray-700 dark:bg-gray-900 rounded-xl overflow-hidden">
                                                    <div
                                                        class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 bg-amber-50/50 dark:bg-amber-900/10">
                                                        <h4
                                                            class="text-xs font-semibold text-amber-700 dark:text-amber-300 uppercase tracking-wider">
                                                            Paket Lain-Lain
                                                        </h4>
                                                        <x-badge variant="gray">{{ count($paket['others']) }} item</x-badge>
                                                    </div>
                                                    <table class="w-full text-xs">
                                                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                                                            <tr class="text-left text-gray-500 uppercase">
                                                                <th class="px-3 py-2 font-medium">Kode</th>
                                                                <th class="px-3 py-2 font-medium">Nama</th>
                                                                <th class="px-3 py-2 font-medium text-right">Harga</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                            @forelse($paket['others'] as $other)
                                                                <tr wire:key="paket-other-{{ ($other['other_id'] ?? '') . '-' . $loop->index }}">
                                                                    <td
                                                                        class="px-3 py-2 font-mono text-xs text-gray-600">
                                                                        {{ $other['other_id'] }}
                                                                    </td>
                                                                    <td class="px-3 py-2">
                                                                        {{ $other['other_desc'] ?? '-' }}
                                                                    </td>
                                                                    <td class="px-3 py-2 text-right font-mono">
                                                                        {{ $this->formatRupiah($other['acto_price'] ?? 0) }}
                                                                    </td>
                                                                </tr>
                                                            @empty
                                                                <tr>
                                                                    <td colspan="3"
                                                                        class="px-3 py-3 text-center text-gray-400 italic">
                                                                        Belum ada paket lain-lain
                                                                    </td>
                                                                </tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>

                                                {{-- Paket Obat --}}
                                                <div
                                                    class="bg-white border border-gray-200 dark:border-gray-700 dark:bg-gray-900 rounded-xl overflow-hidden">
                                                    <div
                                                        class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 dark:border-gray-700 bg-cyan-50/50 dark:bg-cyan-900/10">
                                                        <h4
                                                            class="text-xs font-semibold text-cyan-700 dark:text-cyan-300 uppercase tracking-wider">
                                                            Paket Obat
                                                        </h4>
                                                        <x-badge
                                                            variant="gray">{{ count($paket['products']) }} item</x-badge>
                                                    </div>
                                                    <table class="w-full text-xs">
                                                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                                                            <tr class="text-left text-gray-500 uppercase">
                                                                <th class="px-3 py-2 font-medium">Kode</th>
                                                                <th class="px-3 py-2 font-medium">Produk</th>
                                                                <th class="px-3 py-2 font-medium text-right">Qty</th>
                                                                <th class="px-3 py-2 font-medium text-right">Harga
                                                                    Jual</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                            @forelse($paket['products'] as $prod)
                                                                <tr>
                                                                    <td
                                                                        class="px-3 py-2 font-mono text-xs text-gray-600">
                                                                        {{ $prod['product_id'] }}
                                                                    </td>
                                                                    <td class="px-3 py-2">
                                                                        {{ $prod['product_name'] ?? '-' }}
                                                                    </td>
                                                                    <td class="px-3 py-2 text-right tabular-nums">
                                                                        {{ $prod['actprod_qty'] ?? 1 }}
                                                                    </td>
                                                                    <td class="px-3 py-2 text-right font-mono">
                                                                        {{ $this->formatRupiah($prod['sales_price'] ?? 0) }}
                                                                    </td>
                                                                </tr>
                                                            @empty
                                                                <tr>
                                                                    <td colspan="4"
                                                                        class="px-3 py-3 text-center text-gray-400 italic">
                                                                        Belum ada paket obat
                                                                    </td>
                                                                </tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data jasa medis belum ada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            {{-- Child actions component --}}
            <livewire:pages::master.master-jasa-medis.jasa-medis.master-jasa-medis-actions
                wire:key="master-jm-actions" />
        </div>
    </div>
</div>
