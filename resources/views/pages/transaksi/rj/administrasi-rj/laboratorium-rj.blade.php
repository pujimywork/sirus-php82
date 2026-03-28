<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;

new class extends Component {
    use EmrRJTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $rjLab = [];

    // Inline editing
    public ?int $editingDtl = null;
    public array $editRow = [];

    public array $formEntryLab = [
        'labDesc' => '',
        'labPrice' => '',
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        if ($this->rjNo) {
            $this->findData($this->rjNo);
            $this->isFormLocked = $this->checkRJStatus($this->rjNo);
        }
    }

    /* ===============================
     | FIND DATA
     =============================== */
    private function findData(int $rjNo): void
    {
        $this->rjLab = DB::table('rstxn_rjlabs')
            ->select('lab_dtl', 'lab_desc', 'lab_price')
            ->where('rj_no', $rjNo)
            ->orderBy('lab_dtl')
            ->get()
            ->map(
                fn($r) => [
                    'labDtl' => (int) $r->lab_dtl,
                    'labDesc' => $r->lab_desc,
                    'labPrice' => $r->lab_price,
                ],
            )
            ->toArray();
    }

    /* ===============================
     | REFRESH — event dari parent
     =============================== */
    #[On('administrasi-lab-rj.updated')]
    public function onAdministrasiUpdated(): void
    {
        if ($this->rjNo) {
            $this->findData($this->rjNo);
        }
    }

    /* ===============================
     | INSERT LAB
     =============================== */
    public function insertLab(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validate(
            [
                'formEntryLab.labDesc' => 'bail|required|string|max:255',
                'formEntryLab.labPrice' => 'bail|required|numeric|min:0',
            ],
            [
                'formEntryLab.labDesc.required' => 'Keterangan harus diisi.',
                'formEntryLab.labPrice.required' => 'Tarif harus diisi.',
                'formEntryLab.labPrice.numeric' => 'Tarif harus berupa angka.',
            ],
        );

        try {
            DB::transaction(function () {
                // Lock row RJ — cegah race condition sequence lab_dtl
                $this->lockRJRow($this->rjNo);

                $last = DB::table('rstxn_rjlabs')->select(DB::raw('nvl(max(lab_dtl)+1,1) as lab_dtl_max'))->first();

                DB::table('rstxn_rjlabs')->insert([
                    'lab_dtl' => $last->lab_dtl_max,
                    'rj_no' => $this->rjNo,
                    'lab_desc' => $this->formEntryLab['labDesc'],
                    'lab_price' => $this->formEntryLab['labPrice'],
                ]);

                $this->rjLab[] = [
                    'labDtl' => (int) $last->lab_dtl_max,
                    'labDesc' => $this->formEntryLab['labDesc'],
                    'labPrice' => $this->formEntryLab['labPrice'],
                ];
            });

            $this->reset(['formEntryLab']);
            $this->resetValidation();
            $this->dispatch('focus-input-lab-desc');
            $this->dispatch('administrasi-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Laboratorium berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | INLINE EDIT — START / CANCEL
     =============================== */
    public function startEdit(int $labDtl): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $row = collect($this->rjLab)->firstWhere('labDtl', $labDtl);
        if (!$row) {
            return;
        }

        $this->editingDtl = $labDtl;
        $this->editRow = [
            'labDesc' => $row['labDesc'],
            'labPrice' => $row['labPrice'],
        ];
    }

    public function cancelEdit(): void
    {
        $this->editingDtl = null;
        $this->editRow = [];
        $this->resetValidation();
    }

    /* ===============================
     | INLINE EDIT — SAVE
     =============================== */
    public function saveEdit(): void
    {
        if ($this->isFormLocked || !$this->editingDtl) {
            return;
        }

        $this->validate(
            [
                'editRow.labDesc' => 'bail|required|string|max:255',
                'editRow.labPrice' => 'bail|required|numeric|min:0',
            ],
            [
                'editRow.labDesc.required' => 'Keterangan harus diisi.',
                'editRow.labPrice.required' => 'Tarif harus diisi.',
                'editRow.labPrice.numeric' => 'Tarif harus berupa angka.',
            ],
        );

        try {
            DB::transaction(function () {
                // Lock row RJ — update + read array lokal harus atomik
                $this->lockRJRow($this->rjNo);

                DB::table('rstxn_rjlabs')
                    ->where('lab_dtl', $this->editingDtl)
                    ->update([
                        'lab_desc' => $this->editRow['labDesc'],
                        'lab_price' => $this->editRow['labPrice'],
                    ]);

                $this->rjLab = collect($this->rjLab)
                    ->map(function ($item) {
                        if ($item['labDtl'] !== $this->editingDtl) {
                            return $item;
                        }
                        return array_merge($item, [
                            'labDesc' => $this->editRow['labDesc'],
                            'labPrice' => $this->editRow['labPrice'],
                        ]);
                    })
                    ->toArray();
            });

            $this->editingDtl = null;
            $this->editRow = [];
            $this->dispatch('administrasi-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Laboratorium berhasil diperbarui.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE LAB
     =============================== */
    public function removeLab(int $labDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($labDtl) {
                // Lock row RJ dulu
                $this->lockRJRow($this->rjNo);

                DB::table('rstxn_rjlabs')->where('lab_dtl', $labDtl)->delete();

                $this->rjLab = collect($this->rjLab)->where('labDtl', '!=', $labDtl)->values()->toArray();
            });

            if ($this->editingDtl === $labDtl) {
                $this->cancelEdit();
            }

            $this->dispatch('administrasi-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Laboratorium berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }
};
?>

<div class="space-y-4">

    {{-- LOCKED BANNER --}}
    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Pasien sudah pulang — data laboratorium terkunci, tidak dapat diubah.
        </div>
    @endif

    {{-- FORM INPUT --}}
    @if (!$isFormLocked)
        <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40" x-data
            x-on:focus-input-lab-desc.window="$nextTick(() => $refs.inputLabDesc?.focus())">

            <div class="flex items-end gap-3">
                {{-- Keterangan --}}
                <div class="flex-1">
                    <x-input-label value="Keterangan" class="mb-1" />
                    <x-text-input wire:model="formEntryLab.labDesc" placeholder="Keterangan laboratorium..."
                        class="w-full text-sm" x-ref="inputLabDesc"
                        x-on:keyup.enter="$nextTick(() => $refs.inputLabPrice?.focus())" />
                    @error('formEntryLab.labDesc')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>

                {{-- Tarif --}}
                <div class="w-44">
                    <x-input-label value="Tarif Laborat" class="mb-1" />
                    <x-text-input wire:model="formEntryLab.labPrice" placeholder="Tarif" class="w-full text-sm"
                        x-ref="inputLabPrice" x-on:keyup.enter="$wire.insertLab()" />
                    @error('formEntryLab.labPrice')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>

                {{-- Tombol --}}
                <div class="flex gap-2 pb-0.5">
                    <button type="button" wire:click.prevent="insertLab" wire:loading.attr="disabled"
                        wire:target="insertLab"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold
                            text-white bg-brand-green hover:bg-brand-green/90 disabled:opacity-60
                            dark:bg-brand-lime dark:text-gray-900 transition shadow-sm">
                        <span wire:loading.remove wire:target="insertLab">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                        </span>
                        <span wire:loading wire:target="insertLab"><x-loading class="w-4 h-4" /></span>
                        Tambah
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- TABEL DATA --}}
    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Laboratorium</h3>
            <x-badge variant="gray">{{ count($rjLab) }} item</x-badge>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead
                    class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3 text-right">Tarif Laborat</th>
                        @if (!$isFormLocked)
                            <th class="px-4 py-3 text-center w-28">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($rjLab as $item)
                        @php $isEditing = $editingDtl === $item['labDtl']; @endphp
                        <tr wire:key="lab-row-{{ $item['labDtl'] }}-{{ $isEditing ? 'edit' : 'view' }}" x-data
                            class="{{ $isEditing ? 'bg-blue-50 dark:bg-blue-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-800/40' }} transition">

                            {{-- Keterangan --}}
                            <td class="px-4 py-2">
                                @if ($isEditing)
                                    <x-text-input wire:model="editRow.labDesc" placeholder="Keterangan..."
                                        class="w-full text-sm" x-ref="editLabDesc" x-init="$el.focus();
                                        $el.select()"
                                        x-on:keyup.enter="$nextTick(() => $refs.editLabPrice?.focus())" />
                                    @error('editRow.labDesc')
                                        <x-input-error :messages="$message" class="mt-1" />
                                    @enderror
                                @else
                                    <span class="text-gray-800 dark:text-gray-200">{{ $item['labDesc'] }}</span>
                                @endif
                            </td>

                            {{-- Tarif --}}
                            <td class="px-4 py-2 whitespace-nowrap">
                                @if ($isEditing)
                                    <div class="flex justify-end">
                                        <x-text-input wire:model="editRow.labPrice" placeholder="Tarif"
                                            class="text-sm text-right w-44" x-ref="editLabPrice"
                                            x-on:keyup.enter="$wire.saveEdit()" />
                                    </div>
                                    @error('editRow.labPrice')
                                        <x-input-error :messages="$message" class="mt-1 text-right" />
                                    @enderror
                                @else
                                    <span class="block font-semibold text-right text-gray-800 dark:text-gray-200">
                                        Rp {{ number_format($item['labPrice']) }}
                                    </span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            @if (!$isFormLocked)
                                <td class="px-4 py-2 whitespace-nowrap">
                                    @if ($isEditing)
                                        <div class="flex items-center justify-center gap-1">
                                            <x-secondary-button type="button" wire:click="saveEdit"
                                                wire:loading.attr="disabled" wire:target="saveEdit"
                                                class="px-3 py-1 text-xs text-green-700 border-green-300 hover:bg-green-50 dark:text-green-400 dark:border-green-600 dark:hover:bg-green-900/20">
                                                Simpan
                                            </x-secondary-button>
                                            <x-secondary-button type="button" wire:click="cancelEdit"
                                                class="px-3 py-1 text-xs">
                                                Batal
                                            </x-secondary-button>
                                        </div>
                                    @else
                                        <div class="flex items-center justify-center gap-1">
                                            <x-secondary-button type="button"
                                                wire:click="startEdit({{ $item['labDtl'] }})" class="px-3 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>
                                            <button type="button" wire:click.prevent="removeLab({{ $item['labDtl'] }})"
                                                wire:confirm="Hapus data laboratorium ini?" wire:loading.attr="disabled"
                                                wire:target="removeLab({{ $item['labDtl'] }})"
                                                class="inline-flex items-center justify-center w-8 h-8 text-red-500 transition rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isFormLocked ? 2 : 3 }}"
                                class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Belum ada data laboratorium
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if (!empty($rjLab))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp {{ number_format(collect($rjLab)->sum('labPrice')) }}
                            </td>
                            @if (!$isFormLocked)
                                <td></td>
                            @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
