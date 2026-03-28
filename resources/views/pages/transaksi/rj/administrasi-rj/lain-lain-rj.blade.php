<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-lainlain-rj'];

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $rjLainLain = [];

    // Inline editing (tarif saja yang bisa diedit)
    public ?int $editingDtl = null;
    public array $editRow = [];

    public array $formEntryLainLain = [
        'lainLainId' => '',
        'lainLainDesc' => '',
        'lainLainPrice' => '',
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);

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
        $this->rjLainLain = DB::table('rstxn_rjothers')
            ->join('rsmst_others', 'rsmst_others.other_id', 'rstxn_rjothers.other_id')
            ->select('rstxn_rjothers.rjo_dtl', 'rstxn_rjothers.other_id', 'rsmst_others.other_desc', 'rstxn_rjothers.other_price')
            ->where('rstxn_rjothers.rj_no', $rjNo)
            ->orderBy('rstxn_rjothers.rjo_dtl')
            ->get()
            ->map(
                fn($r) => [
                    'rjotherDtl' => (int) $r->rjo_dtl,
                    'lainLainId' => $r->other_id,
                    'lainLainDesc' => $r->other_desc,
                    'lainLainPrice' => $r->other_price,
                ],
            )
            ->toArray();
    }

    /* ===============================
     | REFRESH — event dari parent
     =============================== */
    #[On('administrasi-lain-lain-rj.updated')]
    public function onAdministrasiUpdated(): void
    {
        if ($this->rjNo) {
            $this->findData($this->rjNo);
        }
    }

    /* ===============================
     | LOV SELECTED — LAIN LAIN
     =============================== */
    #[On('lov.selected.lain-lain-rj')]
    public function onLainLainSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (!$payload) {
            $this->formEntryLainLain['lainLainId'] = '';
            $this->formEntryLainLain['lainLainDesc'] = '';
            $this->formEntryLainLain['lainLainPrice'] = '';
            return;
        }

        $this->formEntryLainLain['lainLainId'] = $payload['other_id'];
        $this->formEntryLainLain['lainLainDesc'] = $payload['other_desc'];
        $this->formEntryLainLain['lainLainPrice'] = $payload['other_price'];

        $this->dispatch('focus-input-tarif-lainlain');
    }

    /* ===============================
     | INSERT LAIN-LAIN
     =============================== */
    public function insertLainLain(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validate(
            [
                'formEntryLainLain.lainLainId' => 'bail|required|exists:rsmst_others,other_id',
                'formEntryLainLain.lainLainDesc' => 'bail|required',
                'formEntryLainLain.lainLainPrice' => 'bail|required|numeric|min:0',
            ],
            [
                'formEntryLainLain.lainLainId.required' => 'Lain-lain harus dipilih.',
                'formEntryLainLain.lainLainId.exists' => 'Lain-lain tidak valid.',
                'formEntryLainLain.lainLainDesc.required' => 'Deskripsi harus diisi.',
                'formEntryLainLain.lainLainPrice.required' => 'Tarif harus diisi.',
                'formEntryLainLain.lainLainPrice.numeric' => 'Tarif harus berupa angka.',
            ],
        );

        try {
            DB::transaction(function () {
                // Lock row RJ — cegah race condition sequence rjo_dtl
                $this->lockRJRow($this->rjNo);

                $last = DB::table('rstxn_rjothers')->select(DB::raw('nvl(max(rjo_dtl)+1,1) as rjo_dtl_max'))->first();

                DB::table('rstxn_rjothers')->insert([
                    'rjo_dtl' => $last->rjo_dtl_max,
                    'rj_no' => $this->rjNo,
                    'other_id' => $this->formEntryLainLain['lainLainId'],
                    'other_price' => $this->formEntryLainLain['lainLainPrice'],
                ]);

                $this->rjLainLain[] = [
                    'rjotherDtl' => (int) $last->rjo_dtl_max,
                    'lainLainId' => $this->formEntryLainLain['lainLainId'],
                    'lainLainDesc' => $this->formEntryLainLain['lainLainDesc'],
                    'lainLainPrice' => $this->formEntryLainLain['lainLainPrice'],
                ];
            });

            $this->resetFormEntry();
            $this->dispatch('focus-lov-lainlain-rj');
            $this->dispatch('administrasi-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Lain-lain berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | INLINE EDIT — START / CANCEL
     =============================== */
    public function startEdit(int $rjotherDtl): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $row = collect($this->rjLainLain)->firstWhere('rjotherDtl', $rjotherDtl);
        if (!$row) {
            return;
        }

        $this->editingDtl = $rjotherDtl;
        $this->editRow = ['lainLainPrice' => $row['lainLainPrice']];
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
            ['editRow.lainLainPrice' => 'bail|required|numeric|min:0'],
            [
                'editRow.lainLainPrice.required' => 'Tarif harus diisi.',
                'editRow.lainLainPrice.numeric' => 'Tarif harus berupa angka.',
            ],
        );

        try {
            DB::transaction(function () {
                // Lock row RJ — update + array lokal harus atomik
                $this->lockRJRow($this->rjNo);

                DB::table('rstxn_rjothers')
                    ->where('rjo_dtl', $this->editingDtl)
                    ->update(['other_price' => $this->editRow['lainLainPrice']]);

                $this->rjLainLain = collect($this->rjLainLain)
                    ->map(function ($item) {
                        if ($item['rjotherDtl'] !== $this->editingDtl) {
                            return $item;
                        }
                        return array_merge($item, ['lainLainPrice' => $this->editRow['lainLainPrice']]);
                    })
                    ->toArray();
            });

            $this->editingDtl = null;
            $this->editRow = [];
            $this->dispatch('administrasi-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Lain-lain berhasil diperbarui.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE LAIN-LAIN
     =============================== */
    public function removeLainLain(int $rjotherDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($rjotherDtl) {
                // Lock row RJ dulu
                $this->lockRJRow($this->rjNo);

                DB::table('rstxn_rjothers')->where('rjo_dtl', $rjotherDtl)->delete();

                $this->rjLainLain = collect($this->rjLainLain)->where('rjotherDtl', '!=', $rjotherDtl)->values()->toArray();
            });

            if ($this->editingDtl === $rjotherDtl) {
                $this->cancelEdit();
            }

            $this->dispatch('administrasi-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Lain-lain berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET FORM ENTRY
     =============================== */
    public function resetFormEntry(): void
    {
        $this->reset(['formEntryLainLain']);
        $this->resetValidation();
        $this->incrementVersion('modal-lainlain-rj');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-lainlain-rj', [$rjNo ?? 'new']) }}">

    {{-- LOCKED BANNER --}}
    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Pasien sudah pulang — data lain-lain terkunci, tidak dapat diubah.
        </div>
    @endif

    {{-- FORM INPUT --}}
    <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40" x-data
        x-on:focus-lov-lainlain-rj.window="$nextTick(() => $refs.lovLainLainRj?.querySelector('input')?.focus())"
        x-on:focus-input-tarif-lainlain.window="$nextTick(() => $refs.inputTarifLainLain?.focus())">

        @if ($isFormLocked)
            <p class="text-sm italic text-gray-400 dark:text-gray-600">Form input dinonaktifkan.</p>
        @elseif (empty($formEntryLainLain['lainLainId']))
            <div x-ref="lovLainLainRj">
                <livewire:lov.lain-lain.lov-lain-lain target="lain-lain-rj" label="Cari Lain-lain"
                    placeholder="Ketik kode/nama lain-lain..."
                    wire:key="lov-lainlain-rj-{{ $rjNo }}-{{ $renderVersions['modal-lainlain-rj'] ?? 0 }}" />
            </div>
        @else
            <div class="flex items-end gap-3">
                {{-- Kode --}}
                <div class="w-28">
                    <x-input-label value="Kode" class="mb-1" />
                    <x-text-input wire:model="formEntryLainLain.lainLainId" disabled class="w-full text-sm" />
                </div>

                {{-- Deskripsi --}}
                <div class="flex-1">
                    <x-input-label value="Lain-lain" class="mb-1" />
                    <x-text-input wire:model="formEntryLainLain.lainLainDesc" disabled class="w-full text-sm" />
                    @error('formEntryLainLain.lainLainId')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>

                {{-- Tarif --}}
                <div class="w-44">
                    <x-input-label value="Tarif" class="mb-1" />
                    <x-text-input wire:model="formEntryLainLain.lainLainPrice" placeholder="Tarif"
                        class="w-full text-sm" x-ref="inputTarifLainLain" x-init="$nextTick(() => $refs.inputTarifLainLain?.focus())"
                        x-on:keyup.enter="$wire.insertLainLain()" />
                    @error('formEntryLainLain.lainLainPrice')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>

                {{-- Tombol --}}
                <div class="flex gap-2 pb-0.5">
                    <button type="button" wire:click.prevent="insertLainLain" wire:loading.attr="disabled"
                        wire:target="insertLainLain"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold
                            text-white bg-brand-green hover:bg-brand-green/90 disabled:opacity-60
                            dark:bg-brand-lime dark:text-gray-900 transition shadow-sm">
                        <span wire:loading.remove wire:target="insertLainLain">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                        </span>
                        <span wire:loading wire:target="insertLainLain"><x-loading class="w-4 h-4" /></span>
                        Tambah
                    </button>
                    <button type="button" wire:click.prevent="resetFormEntry"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium
                            text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800
                            border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Batal
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- TABEL DATA --}}
    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Lain-lain</h3>
            <x-badge variant="gray">{{ count($rjLainLain) }} item</x-badge>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead
                    class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                        @if (!$isFormLocked)
                            <th class="px-4 py-3 text-center w-28">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($rjLainLain as $item)
                        @php $isEditing = $editingDtl === $item['rjotherDtl']; @endphp
                        <tr wire:key="lainlain-row-{{ $item['rjotherDtl'] }}-{{ $isEditing ? 'edit' : 'view' }}" x-data
                            class="{{ $isEditing ? 'bg-blue-50 dark:bg-blue-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-800/40' }} transition">

                            {{-- Kode --}}
                            <td class="px-4 py-2 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ $item['lainLainId'] }}
                            </td>

                            {{-- Deskripsi --}}
                            <td class="px-4 py-2 text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                {{ $item['lainLainDesc'] }}
                            </td>

                            {{-- Tarif --}}
                            <td class="px-4 py-2 whitespace-nowrap">
                                @if ($isEditing)
                                    <div class="flex justify-end">
                                        <x-text-input wire:model="editRow.lainLainPrice" placeholder="Tarif"
                                            class="text-sm text-right w-44" x-ref="editLainLainPrice"
                                            x-init="$el.focus();
                                            $el.select()" x-on:keyup.enter="$wire.saveEdit()" />
                                    </div>
                                    @error('editRow.lainLainPrice')
                                        <x-input-error :messages="$message" class="mt-1 text-right" />
                                    @enderror
                                @else
                                    <span class="block font-semibold text-right text-gray-800 dark:text-gray-200">
                                        Rp {{ number_format($item['lainLainPrice']) }}
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
                                                wire:click="startEdit({{ $item['rjotherDtl'] }})"
                                                class="px-3 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>
                                            <button type="button"
                                                wire:click.prevent="removeLainLain({{ $item['rjotherDtl'] }})"
                                                wire:confirm="Hapus lain-lain ini?" wire:loading.attr="disabled"
                                                wire:target="removeLainLain({{ $item['rjotherDtl'] }})"
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
                            <td colspan="{{ $isFormLocked ? 3 : 4 }}"
                                class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Belum ada data lain-lain
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if (!empty($rjLainLain))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="{{ $isFormLocked ? 2 : 3 }}"
                                class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp {{ number_format(collect($rjLainLain)->sum('lainLainPrice')) }}
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
