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

    /** Cache paket per pact_id: ['pact_id' => ['others' => [...], 'products' => [...]]] */
    public array $paketCache = [];

    /* -------------------- PANEL TARIF PER KELAS (klik baris → panel kanan) -------------------- */
    public ?string $selectedPactId = null;
    public string $selectedPactDesc = '';

    /** Matrix kelas rawat × tarif jasa terpilih: ['id', 'class_id', 'class_desc', 'actp_price', 'actp_price_bpjs'] */
    public array $tarifKelas = [];

    /** Tarif dasar utk inline edit di tabel, key = pact_id: ['pact_price' => int, 'pact_price_bpjs' => int] */
    public array $hargaDasar = [];

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

        $this->paketCache[$pactId] = ['others' => $others, 'products' => $products];
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

        // Sinkronkan panel tarif: jasa terpilih bisa saja di-rename / dihapus.
        if ($this->selectedPactId) {
            $row = DB::table('rsmst_actparamedics')->where('pact_id', $this->selectedPactId)->first();
            if ($row) {
                $this->selectedPactDesc = (string) ($row->pact_desc ?? '');
                $this->loadTarifKelas();
            } else {
                $this->resetPanelTarif();
            }
        }
    }

    /* ===============================
     | PANEL TARIF PER KELAS (rsmst_actpclasses)
     | Klik baris jasa → panel kanan, pola master kamar.
     =============================== */
    public function selectJasa(string $pactId, string $pactDesc): void
    {
        $this->selectedPactId = $pactId;
        $this->selectedPactDesc = $pactDesc;
        $this->loadTarifKelas();
    }

    private function resetPanelTarif(): void
    {
        $this->selectedPactId = null;
        $this->selectedPactDesc = '';
        $this->tarifKelas = [];
    }

    private function loadTarifKelas(): void
    {
        if (!$this->selectedPactId) {
            $this->tarifKelas = [];
            return;
        }

        // Oracle treats '' as NULL — pakai whereNotNull saja.
        $kelas = DB::table('rsmst_class')->whereNotNull('class_desc')->orderBy('class_id')->select('class_id', 'class_desc')->get();

        $existing = DB::table('rsmst_actpclasses')->where('pact_id', $this->selectedPactId)->select('id', 'class_id', 'actp_price', 'actp_price_bpjs')->get()->keyBy('class_id');

        $this->tarifKelas = $kelas
            ->map(function ($k) use ($existing) {
                $row = $existing[$k->class_id] ?? null;
                return [
                    'id' => $row->id ?? null,
                    'class_id' => (int) $k->class_id,
                    'class_desc' => (string) $k->class_desc,
                    'actp_price' => (int) ($row->actp_price ?? 0),
                    'actp_price_bpjs' => (int) ($row->actp_price_bpjs ?? 0),
                ];
            })
            ->values()
            ->toArray();
    }

    /** Upsert satu baris tarif kelas (pola rsmst_docvisits) — baris semua-nol dihapus. */
    private function persistTarifKelasRow(int $idx): void
    {
        $row = $this->tarifKelas[$idx];
        $allZero = (int) $row['actp_price'] === 0 && (int) $row['actp_price_bpjs'] === 0;

        $payloadKelas = [
            'actp_price' => (int) ($row['actp_price'] ?? 0),
            'actp_price_bpjs' => (int) ($row['actp_price_bpjs'] ?? 0),
        ];

        if ($row['id']) {
            if ($allZero) {
                DB::table('rsmst_actpclasses')->where('id', $row['id'])->delete();
                $this->tarifKelas[$idx]['id'] = null;
            } else {
                DB::table('rsmst_actpclasses')->where('id', $row['id'])->update($payloadKelas);
            }
        } elseif (!$allZero) {
            $nextId = (int) (DB::table('rsmst_actpclasses')->max('id') ?? 0) + 1;
            DB::table('rsmst_actpclasses')->insert([
                'id' => $nextId,
                'pact_id' => $this->selectedPactId,
                'class_id' => (int) $row['class_id'],
                ...$payloadKelas,
            ]);
            $this->tarifKelas[$idx]['id'] = $nextId;
        }
    }

    /** Auto-save saat blur/Enter di input panel (x-text-input-number sync via $wire.set). */
    public function updatedTarifKelas($value, string $key): void
    {
        $segments = explode('.', $key); // "{idx}.{field}"
        if (count($segments) !== 2) {
            return;
        }
        [$idx, $field] = $segments;
        $idx = (int) $idx;

        if (!$this->selectedPactId || !isset($this->tarifKelas[$idx]) || !in_array($field, ['actp_price', 'actp_price_bpjs'], true)) {
            return;
        }

        if (!is_numeric($value) || (int) $value < 0) {
            $this->dispatch('toast', type: 'error', message: 'Tarif harus berupa angka.');
            return;
        }

        try {
            $this->persistTarifKelasRow($idx);
            $this->dispatch('toast', type: 'success', message: 'Tarif kelas tersimpan.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
        }
    }

    public function copyTarifKelasDariBaris(int $idxSource): void
    {
        if (!isset($this->tarifKelas[$idxSource])) {
            return;
        }
        $src = $this->tarifKelas[$idxSource];
        foreach ($this->tarifKelas as $i => $row) {
            if ($i === $idxSource) {
                continue;
            }
            $this->tarifKelas[$i]['actp_price'] = $src['actp_price'];
            $this->tarifKelas[$i]['actp_price_bpjs'] = $src['actp_price_bpjs'];
        }

        try {
            DB::transaction(function () {
                foreach (array_keys($this->tarifKelas) as $i) {
                    $this->persistTarifKelasRow($i);
                }
            });
            $this->dispatch('toast', type: 'success', message: 'Tarif disalin ke semua kelas & tersimpan.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
        }
    }

    public function saveTarifKelas(): void
    {
        if (!$this->selectedPactId) {
            return;
        }

        $this->validate(
            [
                'tarifKelas.*.actp_price' => ['nullable', 'numeric', 'min:0'],
                'tarifKelas.*.actp_price_bpjs' => ['nullable', 'numeric', 'min:0'],
            ],
            [
                'tarifKelas.*.actp_price.numeric' => 'Tarif harus berupa angka.',
                'tarifKelas.*.actp_price_bpjs.numeric' => 'Tarif harus berupa angka.',
            ],
        );

        try {
            DB::transaction(function () {
                foreach (array_keys($this->tarifKelas) as $i) {
                    $this->persistTarifKelasRow($i);
                }
            });

            $this->loadTarifKelas();
            $this->dispatch('toast', type: 'success', message: 'Tarif per kelas berhasil disimpan.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
        }
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

        $rows = $query->orderBy('pact_desc')->paginate($this->itemsPerPage);

        // Snapshot tarif dasar halaman ini utk inline edit (binding x-text-input-number).
        foreach ($rows->items() as $r) {
            $this->hargaDasar[$r->pact_id] = [
                'pact_price' => (int) ($r->pact_price ?? 0),
                'pact_price_bpjs' => (int) ($r->pact_price_bpjs ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Inline edit tarif dasar di tabel — auto-save saat blur
     * (x-text-input-number sync via $wire.set, bukan .live).
     */
    public function updatedHargaDasar($value, string $key): void
    {
        $segments = explode('.', $key);
        $field = array_pop($segments);
        $pactId = implode('.', $segments);

        if (!in_array($field, ['pact_price', 'pact_price_bpjs'], true) || $pactId === '') {
            return;
        }

        if (!is_numeric($value) || (int) $value < 0) {
            $this->dispatch('toast', type: 'error', message: 'Tarif harus berupa angka.');
            return;
        }

        DB::table('rsmst_actparamedics')->where('pact_id', $pactId)->update([$field => (int) $value]);
        $this->dispatch('toast', type: 'success', message: 'Tarif dasar tersimpan.');
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

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-canvas border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
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
                        <x-toolbar-refresh-reset :label="null" />
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
            <div class="flex items-center gap-4 px-5 py-2 border-b border-hairline-soft dark:border-gray-800 bg-surface-card dark:bg-gray-800/40 text-xs flex-wrap">
                <div class="flex items-center gap-2">
                    <span
                        class="px-1.5 py-0.5 rounded bg-surface-strong/70 dark:bg-gray-700/60 font-semibold text-[10px] uppercase tracking-wider text-muted dark:text-gray-300">Jasa
                        Medis</span>
                    <div class="flex items-center gap-1.5">
                        <span class="text-muted dark:text-gray-400">Total</span>
                        <span class="font-bold text-ink dark:text-gray-200">{{ $totalRows }}</span>
                    </div>
                    <span class="text-muted-soft dark:text-gray-600">|</span>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="text-muted dark:text-gray-400">Aktif (di halaman ini)</span>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $aktif }}</span>
                    </div>
                    <span class="text-muted-soft dark:text-gray-600">|</span>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block w-2 h-2 rounded-full bg-red-400"></span>
                        <span class="text-muted dark:text-gray-400">Non-Aktif (di halaman ini)</span>
                        <span class="font-bold text-red-500 dark:text-red-400">{{ $nonAktif }}</span>
                    </div>
                </div>
            </div>

            {{-- TABLE (kiri) + PANEL TARIF PER KELAS (kanan) — pola master kamar --}}
            <div class="mt-4 grid grid-cols-1 lg:grid-cols-12 gap-4 flex-1 min-h-0">
            <div class="lg:col-span-7 flex flex-col min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm border-separate border-spacing-y-2">
                        <thead class="sticky top-0 z-10 bg-surface-card dark:bg-gray-800">
                            <tr class="text-left">
                                <th class="px-6 py-3.5 text-sm font-medium text-muted dark:text-gray-400 w-8"></th>
                                <th class="px-6 py-3.5 text-sm font-medium text-muted dark:text-gray-400">Kode</th>
                                <th class="px-6 py-3.5 text-sm font-medium text-muted dark:text-gray-400">Nama</th>
                                <th class="px-6 py-3.5 text-sm font-medium text-muted dark:text-gray-400 text-right">Tarif Umum</th>
                                <th class="px-6 py-3.5 text-sm font-medium text-muted dark:text-gray-400 text-right">Tarif BPJS</th>
                                <th class="px-6 py-3.5 text-sm font-medium text-muted dark:text-gray-400">Status</th>
                                <th class="px-6 py-3.5 text-sm font-medium text-center text-muted dark:text-gray-400">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-body dark:text-gray-400 divide-y divide-hairline dark:divide-gray-700">
                            @forelse($this->rows as $row)
                                @php
                                    $isExpanded = in_array($row->pact_id, $expanded, true);
                                    $isSelected = $selectedPactId === (string) $row->pact_id;
                                @endphp

                                {{-- Klik baris → panel tarif per kelas di kanan --}}
                                <tr wire:key="jm-row-{{ $row->pact_id }}"
                                    wire:click="selectJasa('{{ $row->pact_id }}', '{{ addslashes($row->pact_desc) }}')"
                                    class="cursor-pointer transition rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700 {{ $isSelected ? 'bg-surface-card dark:bg-gray-700 hover:shadow-lg hover:bg-surface-strong dark:hover:bg-gray-600' : 'bg-canvas dark:bg-gray-900 hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800' }}">
                                    <td class="px-6 py-4 text-center" wire:click.stop>
                                        <button type="button" wire:click="toggleExpand('{{ $row->pact_id }}')"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded text-muted hover:bg-surface-strong dark:hover:bg-gray-700 transition"
                                            title="Lihat paket">
                                            <svg class="w-4 h-4 transition-transform {{ $isExpanded ? 'rotate-90' : '' }}"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-sm text-muted dark:text-gray-300">{{ $row->pact_id }}</td>
                                    <td class="px-6 py-4 font-medium text-ink dark:text-white">{{ $row->pact_desc }}</td>
                                    {{-- Tarif dasar — inline edit, auto-save saat blur.
                                         Wrapper w-28 — jangan andalkan w-* di komponen (kalah vs w-full bawaan). --}}
                                    <td class="px-6 py-4" wire:click.stop>
                                        <div class="w-28 ml-auto">
                                            <x-text-input-number wire:model="hargaDasar.{{ $row->pact_id }}.pact_price"
                                                wire:key="hd-umum-{{ $row->pact_id }}" x-on:keydown.enter.prevent="$el.blur()" />
                                        </div>
                                    </td>
                                    <td class="px-6 py-4" wire:click.stop>
                                        <div class="w-28 ml-auto">
                                            <x-text-input-number wire:model="hargaDasar.{{ $row->pact_id }}.pact_price_bpjs"
                                                wire:key="hd-bpjs-{{ $row->pact_id }}" x-on:keydown.enter.prevent="$el.blur()" />
                                        </div>
                                    </td>
                                    <td class="px-6 py-4" wire:click.stop>
                                        <x-toggle :current="(string) ($row->active_status ?? '0')" trueValue="1" falseValue="0"
                                            wireClick="toggleActive('{{ $row->pact_id }}')">
                                            {{ (string) ($row->active_status ?? '0') === '1' ? 'Aktif' : 'Tidak Aktif' }}
                                        </x-toggle>
                                    </td>
                                    <td class="px-6 py-4" wire:click.stop>
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit('{{ $row->pact_id }}')" />
                                            <x-action-delete :action="'requestDelete(\'' . $row->pact_id . '\')'" title="Hapus Jasa Medis"
                                                message="Yakin hapus {{ $row->pact_desc }}? Paket-nya juga ikut terhapus." />
                                        </div>
                                    </td>
                                </tr>

                                {{-- Expand row paket --}}
                                @if ($isExpanded)
                                    @php
                                        $paket = $paketCache[$row->pact_id] ?? ['others' => [], 'products' => []];
                                    @endphp
                                    <tr wire:key="jm-paket-{{ $row->pact_id }}" class="rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700 bg-surface-card dark:bg-gray-800/30">
                                        <td colspan="7" class="px-6 py-4 bg-surface-card dark:bg-gray-800/30">
                                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                                {{-- Paket Lain-Lain --}}
                                                <div
                                                    class="bg-canvas border border-hairline dark:border-gray-700 dark:bg-gray-900 rounded-xl overflow-hidden">
                                                    <div
                                                        class="flex items-center justify-between px-4 py-2.5 border-b border-hairline dark:border-gray-700 bg-amber-50/50 dark:bg-amber-900/10">
                                                        <h4
                                                            class="text-xs font-semibold text-amber-700 dark:text-amber-300 uppercase tracking-wider">
                                                            Paket Lain-Lain
                                                        </h4>
                                                        <x-badge variant="gray">{{ count($paket['others']) }} item</x-badge>
                                                    </div>
                                                    <table class="w-full text-xs">
                                                        <thead class="bg-surface-card dark:bg-gray-800/50">
                                                            <tr class="text-left text-muted uppercase">
                                                                <th class="px-3 py-2 font-medium">Kode</th>
                                                                <th class="px-3 py-2 font-medium">Nama</th>
                                                                <th class="px-3 py-2 font-medium text-right">Harga</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="text-body divide-y divide-hairline-soft dark:divide-gray-800 dark:text-gray-400">
                                                            @forelse($paket['others'] as $other)
                                                                <tr wire:key="paket-other-{{ ($other['other_id'] ?? '') . '-' . $loop->index }}">
                                                                    <td
                                                                        class="px-3 py-2 font-mono text-xs text-muted">
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
                                                                        class="px-3 py-3 text-center text-muted italic">
                                                                        Belum ada paket lain-lain
                                                                    </td>
                                                                </tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>

                                                {{-- Paket Obat --}}
                                                <div
                                                    class="bg-canvas border border-hairline dark:border-gray-700 dark:bg-gray-900 rounded-xl overflow-hidden">
                                                    <div
                                                        class="flex items-center justify-between px-4 py-2.5 border-b border-hairline dark:border-gray-700 bg-cyan-50/50 dark:bg-cyan-900/10">
                                                        <h4
                                                            class="text-xs font-semibold text-cyan-700 dark:text-cyan-300 uppercase tracking-wider">
                                                            Paket Obat
                                                        </h4>
                                                        <x-badge
                                                            variant="gray">{{ count($paket['products']) }} item</x-badge>
                                                    </div>
                                                    <table class="w-full text-xs">
                                                        <thead class="bg-surface-card dark:bg-gray-800/50">
                                                            <tr class="text-left text-muted uppercase">
                                                                <th class="px-3 py-2 font-medium">Kode</th>
                                                                <th class="px-3 py-2 font-medium">Produk</th>
                                                                <th class="px-3 py-2 font-medium text-right">Qty</th>
                                                                <th class="px-3 py-2 font-medium text-right">Harga
                                                                    Jual</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="text-body divide-y divide-hairline-soft dark:divide-gray-800 dark:text-gray-400">
                                                            @forelse($paket['products'] as $prod)
                                                                <tr>
                                                                    <td
                                                                        class="px-3 py-2 font-mono text-xs text-muted">
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
                                                                        class="px-3 py-3 text-center text-muted italic">
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
                                    <td colspan="7" class="px-6 py-10 text-center text-muted dark:text-gray-400">
                                        Data jasa medis belum ada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            {{-- PANEL TARIF PER KELAS (kanan) --}}
            <div class="lg:col-span-5 flex flex-col min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 py-3 border-b border-hairline dark:border-gray-700 rounded-t-2xl">
                    <h3 class="ds-caption-up dark:text-gray-300">Tarif per Kelas Rawat</h3>
                    @if ($selectedPactId)
                        <div class="mt-1 flex items-center gap-2 text-xs">
                            <span class="px-1.5 py-0.5 rounded font-mono font-bold bg-surface-strong/70 dark:bg-gray-700/60 text-ink dark:text-gray-200">{{ $selectedPactId }}</span>
                            <span class="font-semibold text-brand-green dark:text-brand-lime">{{ $selectedPactDesc }}</span>
                        </div>
                    @endif
                </div>

                @if (!$selectedPactId)
                    <div class="flex flex-col items-center justify-center flex-1 py-12 text-muted-soft dark:text-gray-500">
                        <svg class="w-10 h-10 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        <p class="text-sm">Klik baris jasa medis di sebelah kiri untuk kelola tarif per kelas.</p>
                    </div>
                @else
                    <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-3">
                        <div
                            class="flex items-center gap-2 px-3 py-2 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-xl dark:bg-blue-900/20 dark:border-blue-700 dark:text-blue-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Tarif 0 = ikut tarif dasar. Set semua kolom = 0 untuk menghapus tarif kelas tsb.
                        </div>

                        <div class="overflow-hidden border border-hairline dark:border-gray-700 rounded-xl">
                            <table class="w-full text-sm">
                                <thead class="bg-surface-card dark:bg-gray-800/50 text-xs text-muted uppercase">
                                    <tr class="text-left">
                                        <th class="px-3 py-2 font-medium">Kelas</th>
                                        <th class="px-3 py-2 font-medium">Umum</th>
                                        <th class="px-3 py-2 font-medium">BPJS</th>
                                        <th class="px-3 py-2 w-10 text-center font-medium" title="Salin tarif baris ke semua kelas lain">Copy</th>
                                    </tr>
                                </thead>
                                <tbody class="text-body divide-y divide-hairline-soft dark:divide-gray-800 dark:text-gray-400">
                                    @forelse ($tarifKelas as $idx => $rowKelas)
                                        <tr wire:key="tarif-kelas-{{ $selectedPactId }}-{{ $rowKelas['class_id'] }}">
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                <div class="font-semibold text-ink dark:text-gray-200">{{ $rowKelas['class_desc'] }}</div>
                                                <div class="text-xs text-muted font-mono">ID: {{ $rowKelas['class_id'] }}</div>
                                            </td>
                                            <td class="px-2 py-2">
                                                <x-text-input-number wire:model="tarifKelas.{{ $idx }}.actp_price" x-on:keydown.enter.prevent="$el.blur()" />
                                            </td>
                                            <td class="px-2 py-2">
                                                <x-text-input-number wire:model="tarifKelas.{{ $idx }}.actp_price_bpjs" x-on:keydown.enter.prevent="$el.blur()" />
                                            </td>
                                            <td class="px-2 py-2 text-center">
                                                <button type="button" wire:click="copyTarifKelasDariBaris({{ $idx }})"
                                                    wire:confirm="Salin tarif baris ini ke semua kelas lainnya?"
                                                    class="inline-flex items-center justify-center w-7 h-7 text-muted dark:text-gray-300 rounded-lg hover:bg-surface-soft dark:hover:bg-gray-700 transition"
                                                    title="Salin tarif baris ini ke semua kelas lain">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-3 py-6 text-center text-xs text-muted italic">
                                                Data kelas belum tersedia.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700 flex justify-end">
                        <x-primary-button type="button" wire:click="saveTarifKelas"
                            wire:loading.attr="disabled" wire:target="saveTarifKelas">
                            <span wire:loading.remove wire:target="saveTarifKelas">Simpan Tarif</span>
                            <span wire:loading wire:target="saveTarifKelas"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                @endif
            </div>
            </div>

            {{-- Child actions component --}}
            <livewire:pages::master.master-jasa-medis.jasa-medis.master-jasa-medis-actions
                wire:key="master-jm-actions" />
        </div>
    </div>
</div>
