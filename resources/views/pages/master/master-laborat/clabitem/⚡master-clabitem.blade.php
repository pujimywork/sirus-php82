<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    /* --- CLAB terpilih (dari event) --- */
    public ?string $selectedClabId = null;
    public string $selectedClabDesc = '';

    /* --- Filter --- */
    public string $searchItem = '';
    public int $itemsPerPage = 15;

    public function updatedSearchItem(): void
    {
        $this->resetPage('pageItem');
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage('pageItem');
    }

    /* --- Terima CLAB terpilih --- */
    #[On('clab.selected')]
    public function onClabSelected(string $clabId, string $clabDesc): void
    {
        $this->selectedClabId = $clabId;
        $this->selectedClabDesc = $clabDesc;
        $this->searchItem = '';
        $this->resetPage('pageItem');
    }

    /* --- Dispatch ke actions --- */
    public function openCreateClabitem(): void
    {
        if (!$this->selectedClabId) {
            return;
        }
        $this->dispatch('master.laborat.openCreateClabitem', clabId: $this->selectedClabId);
    }

    public function openEditClabitem(string $clabitemId, string $clabId, string $productId): void
    {
        $this->dispatch('master.laborat.openEditClabitem', clabitemId: $clabitemId, clabId: $clabId, productId: $productId);
    }

    public function requestDeleteClabitem(string $clabitemId, string $clabId, string $productId): void
    {
        $this->dispatch('master.laborat.deleteClabitem', clabitemId: $clabitemId, clabId: $clabId, productId: $productId);
    }

    /* --- Refresh setelah save/delete --- */
    #[On('master.laborat.saved')]
    public function afterSaved(string $entity): void
    {
        if ($entity === 'clabitem') {
            unset($this->computedPropertyCache);
            $this->resetPage('pageItem');
        }
    }

    /* --- Query CLABITEM --- */
    #[Computed]
    public function clabitems()
    {
        if (!$this->selectedClabId) {
            return null;
        }

        $q = DB::table('lbmst_clabitems')
            ->select(
                'clabitem_id',
                'clabitem_desc',
                'clab_id',
                'product_id',
                'is_group',
                'clabitem_group',
                'price',
                'dosage',
                'unit_desc',
                'item_seq',
                'item_code',
                'normal_m',
                'normal_f',
                'low_limit_m',
                'high_limit_m',
                'low_limit_f',
                'high_limit_f',
                'low_limit_k',
                'high_limit_k',
                'unit_convert',
                'lowhigh_status',
                'hidden_status',
                'status',
                'loinc_code',
                'loinc_display',
            )
            ->where('clab_id', $this->selectedClabId)
            ->orderBy('item_seq')
            ->orderBy('clabitem_desc');

        if (trim($this->searchItem) !== '') {
            $kw = mb_strtoupper(trim($this->searchItem));
            $q->where(function ($sub) use ($kw) {
                $sub->whereRaw('UPPER(clabitem_desc) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(clabitem_id) LIKE ?', ["%{$kw}%"])
                    ->orWhereRaw('UPPER(item_code) LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage, ['*'], 'pageItem');
    }
};
?>

<div>
    @if ($selectedClabId)
        <div wire:loading.class="opacity-60" wire:target="onClabSelected">

            {{-- Toolbar --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex items-center gap-3 w-full lg:max-w-xs">
                        <x-text-input type="text" wire:model.live.debounce.300ms="searchItem"
                            placeholder="Cari pemeriksaan..." class="block w-full" />
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openCreateClabitem">
                            + Tambah Pemeriksaan
                        </x-primary-button>
                    </div>
                </div>
            </div>

            {{-- Rekap --}}
            @php
                $rekapItems = $this->clabitems;
                $totalItem  = $rekapItems->total();
                $aktifItem  = collect($rekapItems->items())->whereNotIn('status', ['0'])->count();
                $hiddenItem = collect($rekapItems->items())->where('hidden_status', '1')->count();
            @endphp
            <div class="flex items-center gap-3 px-5 py-2 border-b border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 text-xs flex-wrap">
                <div class="flex items-center gap-1.5">
                    <span class="text-gray-400 dark:text-gray-500">Total</span>
                    <span class="font-bold text-gray-700 dark:text-gray-200">{{ $totalItem }} item</span>
                </div>
                <span class="text-gray-200 dark:text-gray-700">&middot;</span>
                <div class="flex items-center gap-1.5">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span class="text-gray-500 dark:text-gray-400">Aktif</span>
                    <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $aktifItem }}</span>
                </div>
                @if ($hiddenItem > 0)
                    <span class="text-gray-200 dark:text-gray-700">&middot;</span>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block w-2 h-2 rounded-full bg-gray-400"></span>
                        <span class="text-gray-500 dark:text-gray-400">Hidden</span>
                        <span class="font-bold text-gray-500 dark:text-gray-400">{{ $hiddenItem }}</span>
                    </div>
                @endif
            </div>

            {{-- Tabel CLABITEM --}}
            <div class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto overflow-y-auto max-h-[calc(100dvh-320px)] rounded-t-2xl">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-5 py-3 font-semibold">
                                    PEMERIKSAAN
                                    <span class="font-normal text-brand dark:text-brand-lime ml-1">&mdash;
                                        {{ $selectedClabDesc }}</span>
                                </th>
                                <th class="px-5 py-3 font-semibold">MAPPING</th>
                                <th class="px-5 py-3 font-semibold text-right">TARIF</th>
                                <th class="px-5 py-3 font-semibold">NILAI RUJUKAN</th>
                                <th class="px-5 py-3 font-semibold">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-200 dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($this->clabitems as $item)
                                @php
                                    $isGroup  = $item->is_group === '1' || $item->is_group === 'Y';
                                    $isHidden = $item->hidden_status === '1';
                                @endphp

                                @php
                                    $isChild = !empty($item->clabitem_group);
                                @endphp

                                <tr wire:key="clabitem-{{ $item->clabitem_id }}-{{ $item->product_id }}"
                                    class="transition
                                           {{ $isGroup ? 'bg-indigo-50 dark:bg-indigo-900/15 font-semibold border-l-4 border-l-indigo-400 dark:border-l-indigo-500' : '' }}
                                           {{ $isChild && !$isGroup ? 'bg-gray-50/50 dark:bg-gray-800/30' : '' }}
                                           {{ $isHidden ? 'opacity-40' : '' }}
                                           hover:bg-gray-50 dark:hover:bg-gray-800/60">

                                    {{-- ITEM --}}
                                    <td class="py-3 align-top space-y-1 {{ $isChild && !$isGroup ? 'pl-10 pr-5' : 'px-5' }}">
                                        <div class="flex items-center gap-2">
                                            @if ($isGroup)
                                                <x-badge variant="info">Paket</x-badge>
                                            @elseif ($isChild)
                                                <span class="text-gray-300 dark:text-gray-600 mr-1">&#x251C;</span>
                                            @endif
                                            <span class="{{ $isGroup ? 'text-indigo-700 dark:text-indigo-300' : 'text-gray-800 dark:text-gray-100' }}">
                                                {{ $item->clabitem_desc }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                            <span class="font-mono">{{ $item->clabitem_id }}</span>
                                            @if ($item->unit_desc)
                                                <span>{{ $item->unit_desc }}</span>
                                            @endif
                                            @if ($item->item_seq)
                                                <span>Seq: {{ $item->item_seq }}</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- MAPPING --}}
                                    <td class="px-5 py-3 align-top">
                                        <div class="space-y-1 text-xs">
                                            @if ($item->item_code)
                                                <div class="flex items-center gap-1.5">
                                                    <span class="px-1.5 py-0.5 rounded font-mono text-[10px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Mindray</span>
                                                    <span class="font-mono text-gray-600 dark:text-gray-300">{{ $item->item_code }}</span>
                                                </div>
                                            @endif
                                            @if ($item->loinc_code)
                                                <div class="flex items-center gap-1.5">
                                                    <span class="px-1.5 py-0.5 rounded font-mono text-[10px] font-bold bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300">LOINC</span>
                                                    <span class="font-mono text-gray-600 dark:text-gray-300">{{ $item->loinc_code }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- TARIF --}}
                                    <td class="px-5 py-3 align-top text-right">
                                        <span class="font-mono font-semibold text-gray-700 dark:text-gray-200">
                                            {{ number_format($item->price ?? 0, 0, ',', '.') }}
                                        </span>
                                    </td>

                                    {{-- NILAI NORMAL --}}
                                    <td class="px-5 py-3 align-top">
                                        <div class="space-y-0.5 text-xs">
                                            @if ($item->lowhigh_status === 'Y' || $item->lowhigh_status === '1')
                                                {{-- Mode low-high --}}
                                                @if ($item->low_limit_m !== null || $item->high_limit_m !== null)
                                                    <div class="text-gray-500 dark:text-gray-400">
                                                        <span class="text-blue-500">P</span>:
                                                        {{ $item->low_limit_m ?? '-' }} - {{ $item->high_limit_m ?? '-' }}
                                                    </div>
                                                @endif
                                                @if ($item->low_limit_f !== null || $item->high_limit_f !== null)
                                                    <div class="text-gray-500 dark:text-gray-400">
                                                        <span class="text-pink-500">W</span>:
                                                        {{ $item->low_limit_f ?? '-' }} - {{ $item->high_limit_f ?? '-' }}
                                                    </div>
                                                @endif
                                                @if ($item->low_limit_k !== null || $item->high_limit_k !== null)
                                                    <div class="text-gray-500 dark:text-gray-400">
                                                        <span class="text-green-500">A</span>:
                                                        {{ $item->low_limit_k ?? '-' }} - {{ $item->high_limit_k ?? '-' }}
                                                    </div>
                                                @endif
                                            @else
                                                {{-- Mode teks --}}
                                                @if ($item->normal_m)
                                                    <div class="text-gray-500 dark:text-gray-400">
                                                        <span class="text-blue-500">P</span>: {{ $item->normal_m }}
                                                    </div>
                                                @endif
                                                @if ($item->normal_f)
                                                    <div class="text-gray-500 dark:text-gray-400">
                                                        <span class="text-pink-500">W</span>: {{ $item->normal_f }}
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="px-5 py-3 align-top">
                                        <div class="flex flex-wrap gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEditClabitem('{{ $item->clabitem_id }}', '{{ $item->clab_id }}', '{{ $item->product_id }}')"
                                                class="px-2 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>
                                            <x-confirm-button variant="danger"
                                                :action="'requestDeleteClabitem(\'' . $item->clabitem_id . '\', \'' . $item->clab_id . '\', \'' . $item->product_id . '\')'"
                                                title="Hapus Item"
                                                message="Yakin hapus item {{ $item->clabitem_desc }}?"
                                                confirmText="Ya, hapus" cancelText="Batal"
                                                class="px-2 py-1 text-xs">
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>

                            @empty
                                <tr>
                                    <td colspan="5"
                                        class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Belum ada pemeriksaan untuk kategori ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->clabitems->links() }}
                </div>
            </div>

        </div>
    @else
        <div class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-gray-500">
            <svg class="w-10 h-10 mb-3 opacity-40" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
            </svg>
            <p class="text-sm">Pilih kategori lab di sebelah kiri untuk melihat daftar pemeriksaan.</p>
        </div>
    @endif
</div>
