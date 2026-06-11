<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public array  $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public ?string $intDesc = null;
    public string $searchProduk = '';

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    // =========================================================
    // TAMBAH PRODUK (picker)
    // =========================================================

    #[On('master.interaksi.openAddProduk')]
    public function openAddProduk(string $intDesc): void
    {
        $this->intDesc = $intDesc;
        $this->searchProduk = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-interaksi-dtl');
        $this->dispatch('focus-search-produk');
    }

    public function updatedSearchProduk(): void
    {
        unset($this->computedPropertyCache);
    }

    /* --- ID produk yang sudah terdaftar di interaksi ini --- */
    #[Computed]
    public function existingIds(): array
    {
        if (! $this->intDesc) {
            return [];
        }

        return DB::table('immst_interaksi_proddtls')
            ->where('int_desc', $this->intDesc)
            ->pluck('product_id')
            ->map(fn($v) => (string) $v)
            ->all();
    }

    /* --- Hasil pencarian produk --- */
    #[Computed]
    public function results()
    {
        $kw = trim($this->searchProduk);
        if ($kw === '' || mb_strlen($kw) < 2) {
            return collect();
        }

        $upper = mb_strtoupper($kw);

        $rows = DB::table('immst_products as p')
            ->select('p.product_id', 'p.product_name', 'p.kode', 'p.takar')
            ->where(function ($q) use ($upper, $kw) {
                if (ctype_digit($kw)) {
                    $q->orWhere('p.product_id', $kw);
                }
                $q->orWhereRaw('UPPER(p.product_name) LIKE ?', ["%{$upper}%"])
                  ->orWhereRaw('UPPER(p.kode) LIKE ?', ["%{$upper}%"])
                  ->orWhereExists(function ($ex) use ($upper) {
                      $ex->select(DB::raw('1'))
                         ->from('immst_productcontents as pc')
                         ->leftJoin('immst_contents as c', 'pc.cont_id', '=', 'c.cont_id')
                         ->whereColumn('pc.product_id', 'p.product_id')
                         ->whereRaw('UPPER(c.cont_desc) LIKE ?', ["%{$upper}%"]);
                  });
            })
            ->orderBy('p.product_name')
            ->limit(25)
            ->get();

        $this->attachKandungan($rows->all());

        return $rows;
    }

    /* --- Lampirkan daftar kandungan (zat aktif) ke tiap item produk --- */
    private function attachKandungan(array $items): void
    {
        $ids = array_values(array_unique(array_filter(array_map(fn($i) => $i->product_id ?? null, $items))));

        $map = [];
        if (! empty($ids)) {
            $rows = DB::table('immst_productcontents as pc')
                ->leftJoin('immst_contents as c', 'pc.cont_id', '=', 'c.cont_id')
                ->leftJoin('immst_uoms as u', 'pc.uom_id', '=', 'u.uom_id')
                ->whereIn('pc.product_id', $ids)
                ->orderBy('pc.product_id')
                ->orderBy('pc.prc_dtl')
                ->get(['pc.product_id', 'c.cont_desc', 'pc.prc_qty', 'u.uom_desc']);

            foreach ($rows as $r) {
                $map[$r->product_id][] = $r;
            }
        }

        foreach ($items as $item) {
            $item->kandungan_list = $map[$item->product_id] ?? [];
        }
    }

    /* --- Tambah satu produk ke interaksi --- */
    public function addProduk(string $productId): void
    {
        if (! $this->intDesc) {
            return;
        }

        $exists = DB::table('immst_interaksi_proddtls')
            ->where('int_desc', $this->intDesc)
            ->where('product_id', $productId)
            ->exists();

        if ($exists) {
            $this->dispatch('toast', type: 'info', message: 'Produk sudah ada di interaksi ini.');
            return;
        }

        DB::table('immst_interaksi_proddtls')->insert([
            'int_desc'   => $this->intDesc,
            'product_id' => $productId,
        ]);

        unset($this->computedPropertyCache);
        $this->dispatch('toast', type: 'success', message: 'Produk ditambahkan.');
        $this->dispatch('master.interaksi.produkSaved', intDesc: $this->intDesc);
    }

    // =========================================================
    // HAPUS PRODUK
    // =========================================================

    #[On('master.interaksi.deleteProduk')]
    public function deleteProduk(string $intDesc, string $productId): void
    {
        try {
            $deleted = DB::table('immst_interaksi_proddtls')
                ->where('int_desc', $intDesc)
                ->where('product_id', $productId)
                ->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Produk tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Produk dikeluarkan dari interaksi.');
            $this->dispatch('master.interaksi.produkSaved', intDesc: $intDesc);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Produk tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function closeModal(): void
    {
        $this->searchProduk = '';
        $this->dispatch('close-modal', name: 'master-interaksi-dtl');
        $this->resetVersion();
    }
};
?>

<div>
    <x-modal name="master-interaksi-dtl" size="lg" height="auto" focusable>
        <div :wire:key="$this->renderKey('modal')" class="flex flex-col min-h-0">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 bg-surface-soft">
                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                            <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo" class="block w-6 h-6 dark:hidden" />
                            <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo" class="hidden w-6 h-6 dark:block" />
                        </div>
                        <div>
                            <h2 class="ds-display-sm dark:text-gray-100">Tambah Produk</h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                Cari & klik produk untuk dimasukkan ke interaksi
                                <span class="font-semibold text-brand dark:text-brand-lime">{{ $intDesc }}</span>.
                            </p>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Tutup</span>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-6 py-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain
                x-data
                x-on:focus-search-produk.window="$nextTick(() => setTimeout(() => $refs.inputSearchProduk?.focus(), 150))">

                <x-input-label value="Cari Produk" />
                <x-text-input wire:model.live.debounce.300ms="searchProduk" x-ref="inputSearchProduk"
                    placeholder="Ketik minimal 2 karakter — nama / kode / ID produk..." class="w-full mt-1" />

                <div class="mt-3 border border-hairline dark:border-gray-700 rounded-xl overflow-hidden bg-canvas dark:bg-gray-900">
                    <div class="max-h-80 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                        @php $existing = $this->existingIds; @endphp
                        @forelse ($this->results as $p)
                            @php $already = in_array((string) $p->product_id, $existing, true); @endphp
                            <div class="flex items-center justify-between gap-3 px-4 py-3 {{ $already ? 'opacity-60' : 'hover:bg-surface-soft dark:hover:bg-gray-800/60' }}">
                                <div class="min-w-0">
                                    <div class="font-medium text-gray-800 dark:text-gray-100 truncate">{{ $p->product_name }}</div>
                                    <div class="flex flex-wrap items-center gap-x-3 text-xs" style="color:var(--muted)">
                                        <span class="font-mono">{{ $p->product_id }}</span>
                                        @if ($p->kode)
                                            <span class="font-mono">{{ $p->kode }}</span>
                                        @endif
                                        @if ($p->takar)
                                            <span class="text-gray-400 dark:text-gray-500">{{ $p->takar }}</span>
                                        @endif
                                    </div>
                                    @if (! empty($p->kandungan_list))
                                        <div class="flex flex-wrap items-center gap-1 mt-1">
                                            @foreach ($p->kandungan_list as $k)
                                                <span class="px-1.5 py-0.5 rounded text-[11px] font-medium
                                                             bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                                    {{ $k->cont_desc ?? '-' }}@if ($k->prc_qty !== null && $k->prc_qty !== '')<span class="opacity-70"> {{ rtrim(rtrim(number_format((float) $k->prc_qty, 2, '.', ''), '0'), '.') }}{{ $k->uom_desc ? ' ' . $k->uom_desc : '' }}</span>@endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                @if ($already)
                                    <x-badge variant="success">Sudah ditambahkan</x-badge>
                                @else
                                    <x-primary-button type="button"
                                        wire:click="addProduk(@js($p->product_id))"
                                        wire:loading.attr="disabled" class="px-3 py-1 text-xs shrink-0">
                                        + Tambah
                                    </x-primary-button>
                                @endif
                            </div>
                        @empty
                            <div class="px-4 py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                                @if (mb_strlen(trim($searchProduk)) < 2)
                                    Ketik minimal 2 karakter untuk mencari produk.
                                @else
                                    Tidak ada produk yang cocok.
                                @endif
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" wire:click="closeModal">Selesai</x-secondary-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
