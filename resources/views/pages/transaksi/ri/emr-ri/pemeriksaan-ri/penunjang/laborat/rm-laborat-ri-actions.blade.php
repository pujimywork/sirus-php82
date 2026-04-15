<?php
// resources/views/pages/transaksi/ri/emr-ri/pemeriksaan-ri/penunjang/laborat/rm-laboratorium-ri-actions.blade.php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait, EmrRITrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['laborat-order-modal-ri'];

    public ?string $riHdrNo = null;
    public bool $disabled = false;
    public array $labList = []; // dari JSON: pemeriksaan.pemeriksaanPenunjang.lab

    /* ── State Modal ── */
    public string $searchItem = '';
    public array $selectedItems = []; // [ clabitem_id => [...item] ]

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->disabled = $disabled;
        $this->registerAreas(['laborat-order-modal-ri']);

        if ($riHdrNo) {
            $this->loadLabList($riHdrNo);
        }
    }

    /* ═══════════════════════════════════════
    | OPEN via parent event
    ═══════════════════════════════════════ */
    #[On('open-rm-laboratorium-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }
        $this->riHdrNo = $riHdrNo;
        $this->loadLabList($riHdrNo);
    }

    /* ═══════════════════════════════════════
    | OPEN / CLOSE ORDER MODAL
    ═══════════════════════════════════════ */
    public function openModal(): void
    {
        if ($this->disabled) {
            return;
        }

        $this->selectedItems = [];
        $this->searchItem = '';
        $this->resetPage();

        $version = $this->renderVersions['laborat-order-modal-ri'] ?? 0;
        $this->dispatch('open-modal', name: "laborat-order-ri-{$version}");
    }

    public function closeModal(): void
    {
        $version = $this->renderVersions['laborat-order-modal-ri'] ?? 0;
        $this->dispatch('close-modal', name: "laborat-order-ri-{$version}");
        $this->reset(['selectedItems', 'searchItem']);
        $this->incrementVersion('laborat-order-modal-ri');
    }

    /* ═══════════════════════════════════════
    | QUERY ITEM LAB (paginated + search)
    ═══════════════════════════════════════ */
    #[Computed]
    public function items()
    {
        $search = trim($this->searchItem);

        return DB::table('lbmst_clabitems')->select('clabitem_id', 'clabitem_desc', 'price', 'clabitem_group', 'item_code')->whereNull('clabitem_group')->whereNotNull('clabitem_desc')->when($search, fn($q) => $q->whereRaw('UPPER(clabitem_desc) LIKE ?', ['%' . mb_strtoupper($search) . '%']))->orderBy('clabitem_desc')->paginate(15);
    }

    /* ═══════════════════════════════════════
    | TOGGLE / REMOVE SELECTED
    ═══════════════════════════════════════ */
    public function toggleItem(string $id, string $desc, ?float $price, ?string $itemCode): void
    {
        if (isset($this->selectedItems[$id])) {
            unset($this->selectedItems[$id]);
        } else {
            $this->selectedItems[$id] = [
                'clabitem_id' => $id,
                'clabitem_desc' => $desc,
                'price' => $price,
                'item_code' => $itemCode,
            ];
        }
    }

    public function isSelected(string $id): bool
    {
        return isset($this->selectedItems[$id]);
    }

    public function removeSelected(string $id): void
    {
        unset($this->selectedItems[$id]);
    }

    /* ═══════════════════════════════════════
    | KIRIM ORDER LABORATORIUM RI
    ═══════════════════════════════════════ */
    public function kirimLaboratorium(): void
    {
        if (empty($this->selectedItems)) {
            $this->dispatch('toast', type: 'warning', message: 'Pilih minimal satu item pemeriksaan.');
            return;
        }

        if ($this->checkRIStatus($this->riHdrNo)) {
            // ← trait, ganti DB::scalar
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, tidak dapat menambah pemeriksaan.');
            return;
        }

        $riData = DB::table('rstxn_rihdrs')->select('reg_no', 'dr_id')->where('rihdr_no', $this->riHdrNo)->first();

        if (!$riData) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        try {
            DB::transaction(function () use ($riData) {
                $this->lockRIRow($this->riHdrNo);

                $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
                $checkupNo = DB::scalar('SELECT NVL(MAX(TO_NUMBER(checkup_no)) + 1, 1) FROM lbtxn_checkuphdrs');

                DB::table('lbtxn_checkuphdrs')->insert([
                    'checkup_no' => $checkupNo,
                    'reg_no' => $riData->reg_no,
                    'dr_id' => $riData->dr_id,
                    'checkup_date' => DB::raw("TO_DATE('{$now}','dd/mm/yyyy hh24:mi:ss')"),
                    'status_rjri' => 'RI',
                    'checkup_status' => 'P',
                    'ref_no' => $this->riHdrNo,
                ]);

                foreach ($this->selectedItems as $item) {
                    $this->insertItemAndChildren($checkupNo, $item);
                }

                $data = $this->findDataRI($this->riHdrNo) ?? [];
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan saat akan disimpan.');
                }

                $labList = $data['pemeriksaan']['pemeriksaanPenunjang']['lab'] ?? [];
                $labList[] = [
                    'labHdr' => [
                        'labHdrNo' => $checkupNo,
                        'labHdrDate' => $now,
                        'labDtl' => array_values($this->selectedItems),
                    ],
                ];
                $data['pemeriksaan']['pemeriksaanPenunjang']['lab'] = $labList;
                $this->updateJsonRI($this->riHdrNo, $data);
            });

            $this->loadLabList($this->riHdrNo);
            $this->dispatch('toast', type: 'success', message: count($this->selectedItems) . ' item lab berhasil dikirim.');
            $this->closeModal();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengirim: ' . $e->getMessage());
        }
    }

    /* ── helpers ── */
    private function loadLabList(string $riHdrNo): void
    {
        $data = $this->findDataRI($riHdrNo);
        $this->labList = $data['pemeriksaan']['pemeriksaanPenunjang']['lab'] ?? [];
    }

    private function insertItemAndChildren(int $checkupNo, array $item): void
    {
        $dtlNo = DB::scalar('SELECT NVL(TO_NUMBER(MAX(checkup_dtl)) + 1, 1) FROM lbtxn_checkupdtls');
        DB::table('lbtxn_checkupdtls')->insert([
            'clabitem_id' => $item['clabitem_id'],
            'checkup_no' => $checkupNo,
            'checkup_dtl' => $dtlNo,
            'lab_item_code' => $item['item_code'],
            'price' => $item['price'],
        ]);

        $children = DB::table('lbmst_clabitems')->select('clabitem_id', 'item_code', 'price')->where('clabitem_group', $item['clabitem_id'])->orderBy('item_seq')->orderBy('clabitem_desc')->get();

        foreach ($children as $child) {
            $childDtlNo = DB::scalar('SELECT NVL(TO_NUMBER(MAX(checkup_dtl)) + 1, 1) FROM lbtxn_checkupdtls');
            DB::table('lbtxn_checkupdtls')->insert([
                'clabitem_id' => $child->clabitem_id,
                'checkup_no' => $checkupNo,
                'checkup_dtl' => $childDtlNo,
                'lab_item_code' => $child->item_code,
                'price' => $child->price,
            ]);
        }
    }
};
?>

<div wire:key="laboratorium-ri-{{ $riHdrNo ?? 'new' }}">

    {{-- Tombol Order --}}
    @if (!$disabled)
        <div class="mb-3">
            <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled" wire:target="openModal">
                <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Order Laboratorium
                </span>
                <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                    <x-loading /> Memuat...
                </span>
            </x-primary-button>
        </div>
    @endif

    {{-- Display dari JSON array --}}
    @if (empty($labList))
        <p class="py-6 text-sm text-center text-gray-400 italic">Belum ada data laboratorium.</p>
    @else
        <div class="space-y-3">
            @foreach ($labList as $batch)
                @php $hdr = $batch['labHdr'] ?? []; @endphp
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="px-3 py-1.5 bg-gray-50 dark:bg-gray-800 text-xs text-gray-500 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
                        <span>{{ $hdr['labHdrDate'] ?? '-' }}</span>
                        @if (!empty($hdr['labHdrNo']))
                            <span class="font-mono">No. Checkup: {{ $hdr['labHdrNo'] }}</span>
                        @endif
                    </div>
                    <table class="w-full text-xs text-left">
                        <thead class="bg-white dark:bg-gray-900 text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                            <tr>
                                <th class="px-3 py-2">Pemeriksaan</th>
                                <th class="px-3 py-2 text-right">Harga</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($hdr['labDtl'] ?? [] as $dtl)
                                <tr class="bg-white dark:bg-gray-900">
                                    <td class="px-3 py-2 font-medium text-gray-800 dark:text-gray-200">
                                        {{ $dtl['clabitem_desc'] ?? '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">
                                        Rp {{ number_format($dtl['price'] ?? 0, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ═══════════ MODAL ORDER LAB RI ═══════════ --}}
    <x-modal name="laborat-order-ri-{{ $renderVersions['laborat-order-modal-ri'] ?? 0 }}" size="full" height="full"
        focusable>
        <div class="flex flex-col h-full"
            wire:key="{{ $this->renderKey('laborat-order-modal-ri', [$riHdrNo ?? 'new']) }}">

            {{-- Header --}}
            <div class="relative px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <div class="absolute inset-0 opacity-[0.05]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);background-size:14px 14px">
                </div>
                <div class="relative flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand/10 dark:bg-brand/15">
                            <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.78 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Order Pemeriksaan
                                Laboratorium</h2>
                            <p class="text-xs text-gray-500">No. RI: <span
                                    class="font-mono font-medium">{{ $riHdrNo }}</span></p>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- Display Pasien RI --}}
            <div class="border-b border-gray-200 dark:border-gray-700 shrink-0">
                <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                    wire:key="display-pasien-ri-lab-{{ $riHdrNo }}" />
            </div>

            {{-- Selected chips --}}
            @if (!empty($selectedItems))
                <div class="px-6 py-3 border-b border-gray-100 dark:border-gray-700 bg-brand/5 shrink-0">
                    <p class="mb-2 text-xs font-semibold text-brand">{{ count($selectedItems) }} item dipilih:</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($selectedItems as $id => $sel)
                            <span
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium border rounded-full bg-brand/10 text-brand border-brand/20">
                                {{ $sel['clabitem_desc'] }}
                                @if ($sel['price'])
                                    <span class="text-brand/60">· {{ number_format($sel['price']) }}</span>
                                @endif
                                <button type="button" wire:click="removeSelected('{{ $id }}')"
                                    class="ml-0.5 hover:text-red-500 transition-colors">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Search --}}
            <div class="px-6 py-3 border-b border-gray-100 dark:border-gray-700 shrink-0">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="searchItem"
                        placeholder="Cari item pemeriksaan..."
                        class="w-full py-2 pl-10 pr-4 text-sm border border-gray-300 rounded-lg
                                  focus:ring-2 focus:ring-brand/30 focus:border-brand
                                  dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100" />
                </div>
            </div>

            {{-- Item Grid --}}
            <div class="flex-1 p-5 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    @forelse ($this->items as $item)
                        @php $selected = $this->isSelected($item->clabitem_id); @endphp
                        <button type="button"
                            wire:click="toggleItem('{{ $item->clabitem_id }}', '{{ addslashes($item->clabitem_desc) }}', {{ $item->price ?? 'null' }}, '{{ $item->item_code }}')"
                            class="relative flex flex-col items-center justify-center p-3 rounded-xl border-2 text-center transition-all
                                   {{ $selected
                                       ? 'border-brand bg-brand/10 text-brand shadow-sm'
                                       : 'border-gray-200 bg-white hover:border-brand/40 hover:bg-brand/5 text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300' }}">

                            @if ($selected)
                                <span
                                    class="absolute top-1.5 right-1.5 flex items-center justify-center w-4 h-4 bg-brand rounded-full">
                                    <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                            @endif

                            <p class="text-xs font-medium leading-tight">{{ $item->clabitem_desc }}</p>

                            @if ($item->price)
                                <p class="mt-1 text-[10px] {{ $selected ? 'text-brand/70' : 'text-gray-400' }}">
                                    {{ number_format($item->price) }}
                                </p>
                            @endif
                        </button>
                    @empty
                        <div class="py-12 text-center text-gray-400 col-span-full">
                            <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <p class="text-sm">Tidak ada item ditemukan</p>
                        </div>
                    @endforelse
                </div>

                @if ($this->items->hasPages())
                    <div class="mt-4">{{ $this->items->links() }}</div>
                @endif
            </div>

            {{-- Footer --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700 shrink-0">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        @if (!empty($selectedItems))
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1 text-sm font-medium text-brand bg-brand/10 border border-brand/30 rounded-full">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                {{ count($selectedItems) }} item dipilih
                            </span>
                        @else
                            <span class="text-xs italic text-gray-400">Klik item untuk memilih pemeriksaan</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <x-secondary-button wire:click="closeModal">Batal</x-secondary-button>
                        @if (!empty($selectedItems))
                            <x-primary-button type="button" wire:click="kirimLaboratorium"
                                wire:loading.attr="disabled" wire:target="kirimLaboratorium">
                                <span wire:loading.remove wire:target="kirimLaboratorium"
                                    class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                    Kirim Order
                                </span>
                                <span wire:loading wire:target="kirimLaboratorium" class="flex items-center gap-1.5">
                                    <x-loading /> Mengirim...
                                </span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </x-modal>

</div>
