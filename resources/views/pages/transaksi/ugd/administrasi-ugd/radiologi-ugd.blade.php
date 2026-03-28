<?php
// resources/views/pages/transaksi/ugd/administrasi-ugd/radiologi-ugd.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-radiologi-ugd'];

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $rjRad = [];

    public ?int $editingDtl = null;
    public array $editRow = [];

    public array $formEntryRad = [
        'radId' => '',
        'radDesc' => '',
        'radPrice' => '',
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
        if ($this->rjNo) {
            $this->loadData($this->rjNo);
            $this->isFormLocked = $this->checkUGDStatus($this->rjNo);
        }
    }

    /* ===============================
     | LOAD DATA
     =============================== */
    private function loadData(int $rjNo): void
    {
        $rows = DB::table('rstxn_ugdrads')->join('rsmst_radiologis', 'rsmst_radiologis.rad_id', 'rstxn_ugdrads.rad_id')->select('rstxn_ugdrads.rad_dtl', 'rstxn_ugdrads.rad_id', 'rsmst_radiologis.rad_desc', 'rstxn_ugdrads.rad_price')->where('rj_no', $rjNo)->orderBy('rstxn_ugdrads.rad_dtl')->get();

        $this->rjRad = $rows
            ->map(
                fn($r) => [
                    'radDtl' => (int) $r->rad_dtl,
                    'radId' => $r->rad_id,
                    'radDesc' => $r->rad_desc,
                    'radPrice' => $r->rad_price,
                ],
            )
            ->toArray();
    }

    /* ===============================
     | LISTENER — refresh dari parent
     =============================== */
    #[On('administrasi-rad-ugd.updated')]
    public function onAdministrasiUpdated(): void
    {
        if ($this->rjNo) {
            $this->loadData($this->rjNo);
        }
    }

    /* ===============================
     | LOV SELECTED
     =============================== */
    #[On('lov.selected.radiologi-ugd')]
    public function onRadiologiSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (!$payload) {
            $this->formEntryRad['radId'] = '';
            $this->formEntryRad['radDesc'] = '';
            $this->formEntryRad['radPrice'] = '';
            return;
        }

        $this->formEntryRad['radId'] = $payload['rad_id'];
        $this->formEntryRad['radDesc'] = $payload['rad_desc'];
        $this->formEntryRad['radPrice'] = $payload['rad_price'];
        $this->dispatch('focus-input-tarif-rad');
    }

    /* ===============================
     | INSERT
     =============================== */
    public function insertRad(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $this->validate(
            [
                'formEntryRad.radId' => 'bail|required|exists:rsmst_radiologis,rad_id',
                'formEntryRad.radDesc' => 'bail|required',
                'formEntryRad.radPrice' => 'bail|required|numeric|min:0',
            ],
            [
                'formEntryRad.radId.required' => 'Radiologi harus dipilih.',
                'formEntryRad.radId.exists' => 'Radiologi tidak valid.',
                'formEntryRad.radDesc.required' => 'Deskripsi radiologi harus diisi.',
                'formEntryRad.radPrice.required' => 'Tarif harus diisi.',
                'formEntryRad.radPrice.numeric' => 'Tarif harus berupa angka.',
            ],
        );

        try {
            DB::transaction(function () {
                // Lock header row — cegah race condition nvl(max)+1
                $this->lockUGDRow($this->rjNo);

                $last = DB::table('rstxn_ugdrads')->select(DB::raw('nvl(max(rad_dtl)+1,1) as rad_dtl_max'))->first();

                DB::table('rstxn_ugdrads')->insert([
                    'rad_dtl' => $last->rad_dtl_max,
                    'rj_no' => $this->rjNo,
                    'rad_id' => $this->formEntryRad['radId'],
                    'rad_price' => $this->formEntryRad['radPrice'],
                ]);

                $this->rjRad[] = [
                    'radDtl' => (int) $last->rad_dtl_max,
                    'radId' => $this->formEntryRad['radId'],
                    'radDesc' => $this->formEntryRad['radDesc'],
                    'radPrice' => $this->formEntryRad['radPrice'],
                ];
            });

            // Notify + reset — di luar transaksi
            $this->resetFormEntry();
            $this->dispatch('focus-lov-radiologi-ugd');
            $this->dispatch('administrasi-ugd.updated');
            $this->dispatch('toast', type: 'success', message: 'Radiologi berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | INLINE EDIT
     =============================== */
    public function startEdit(int $radDtl): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $row = collect($this->rjRad)->firstWhere('radDtl', $radDtl);
        if (!$row) {
            return;
        }

        $this->editingDtl = $radDtl;
        $this->editRow = ['radPrice' => $row['radPrice']];
    }

    public function cancelEdit(): void
    {
        $this->editingDtl = null;
        $this->editRow = [];
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        if ($this->isFormLocked || !$this->editingDtl) {
            return;
        }

        $this->validate(
            ['editRow.radPrice' => 'bail|required|numeric|min:0'],
            [
                'editRow.radPrice.required' => 'Tarif harus diisi.',
                'editRow.radPrice.numeric' => 'Tarif harus berupa angka.',
            ],
        );

        try {
            DB::transaction(function () {
                // Lock header row
                $this->lockUGDRow($this->rjNo);

                DB::table('rstxn_ugdrads')
                    ->where('rad_dtl', $this->editingDtl)
                    ->update(['rad_price' => $this->editRow['radPrice']]);

                $this->rjRad = collect($this->rjRad)->map(fn($item) => $item['radDtl'] !== $this->editingDtl ? $item : array_merge($item, ['radPrice' => $this->editRow['radPrice']]))->toArray();
            });

            // Reset edit state + notify — di luar transaksi
            $this->editingDtl = null;
            $this->editRow = [];
            $this->dispatch('toast', type: 'success', message: 'Radiologi berhasil diperbarui.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE
     =============================== */
    public function removeRad(int $radDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($radDtl) {
                // Lock header row
                $this->lockUGDRow($this->rjNo);

                DB::table('rstxn_ugdrads')->where('rad_dtl', $radDtl)->delete();

                $this->rjRad = collect($this->rjRad)->where('radDtl', '!=', $radDtl)->values()->toArray();
            });

            // cancelEdit + notify — di luar transaksi
            if ($this->editingDtl === $radDtl) {
                $this->cancelEdit();
            }
            $this->dispatch('administrasi-ugd.updated');
            $this->dispatch('toast', type: 'success', message: 'Radiologi berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET FORM
     =============================== */
    public function resetFormEntry(): void
    {
        $this->reset(['formEntryRad']);
        $this->resetValidation();
        $this->incrementVersion('modal-radiologi-ugd');
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-radiologi-ugd', [$rjNo ?? 'new']) }}">

    {{-- LOCKED BANNER --}}
    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Pasien sudah pulang — data radiologi terkunci, tidak dapat diubah.
        </div>
    @endif

    {{-- FORM INPUT --}}
    <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40" x-data
        x-on:focus-lov-radiologi-ugd.window="$nextTick(() => $refs.lovRadiologiUgd?.querySelector('input')?.focus())"
        x-on:focus-input-tarif-rad.window="$nextTick(() => $refs.inputTarifRad?.focus())">

        @if ($isFormLocked)
            <p class="text-sm italic text-gray-400 dark:text-gray-600">Form input dinonaktifkan.</p>
        @elseif (empty($formEntryRad['radId']))
            <div x-ref="lovRadiologiUgd">
                <livewire:lov.radiologi.lov-radiologi target="radiologi-ugd" label="Cari Radiologi"
                    placeholder="Ketik kode/nama radiologi..."
                    wire:key="lov-rad-ugd-{{ $rjNo }}-{{ $renderVersions['modal-radiologi-ugd'] ?? 0 }}" />
            </div>
        @else
            <div class="flex items-end gap-3">
                <div class="w-28">
                    <x-input-label value="Kode" class="mb-1" />
                    <x-text-input wire:model="formEntryRad.radId" disabled class="w-full text-sm" />
                </div>
                <div class="flex-1">
                    <x-input-label value="Radiologi" class="mb-1" />
                    <x-text-input wire:model="formEntryRad.radDesc" disabled class="w-full text-sm" />
                    @error('formEntryRad.radId')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>
                <div class="w-44">
                    <x-input-label value="Tarif" class="mb-1" />
                    <x-text-input wire:model="formEntryRad.radPrice" placeholder="Tarif" class="w-full text-sm"
                        x-ref="inputTarifRad" x-init="$nextTick(() => $refs.inputTarifRad?.focus())" x-on:keyup.enter="$wire.insertRad()" />
                    @error('formEntryRad.radPrice')
                        <x-input-error :messages="$message" class="mt-1" />
                    @enderror
                </div>
                <div class="flex gap-2 pb-0.5">
                    <button type="button" wire:click.prevent="insertRad" wire:loading.attr="disabled"
                        wire:target="insertRad"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-brand-green hover:bg-brand-green/90 disabled:opacity-60 dark:bg-brand-lime dark:text-gray-900 transition shadow-sm">
                        <span wire:loading.remove wire:target="insertRad">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                        </span>
                        <span wire:loading wire:target="insertRad"><x-loading class="w-4 h-4" /></span>
                        Tambah
                    </button>
                    <button type="button" wire:click.prevent="resetFormEntry"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
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
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Radiologi</h3>
            <x-badge variant="gray">{{ count($rjRad) }} item</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead
                    class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3 text-right">Tarif Radiologi</th>
                        @if (!$isFormLocked)
                            <th class="px-4 py-3 text-center w-28">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($rjRad as $item)
                        @php $isEditing = $editingDtl === $item['radDtl']; @endphp
                        <tr wire:key="rad-row-{{ $item['radDtl'] }}-{{ $isEditing ? 'edit' : 'view' }}" x-data
                            class="{{ $isEditing ? 'bg-blue-50 dark:bg-blue-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-800/40' }} transition">

                            <td class="px-4 py-2 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ $item['radId'] }}</td>
                            <td class="px-4 py-2 text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                {{ $item['radDesc'] }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                @if ($isEditing)
                                    <div class="flex justify-end">
                                        <x-text-input wire:model="editRow.radPrice" placeholder="Tarif"
                                            class="text-sm text-right w-44" x-ref="editRadPrice" x-init="$el.focus();
                                            $el.select()"
                                            x-on:keyup.enter="$wire.saveEdit()" />
                                    </div>
                                    @error('editRow.radPrice')
                                        <x-input-error :messages="$message" class="mt-1 text-right" />
                                    @enderror
                                @else
                                    <span class="block font-semibold text-right text-gray-800 dark:text-gray-200">Rp
                                        {{ number_format($item['radPrice']) }}</span>
                                @endif
                            </td>

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
                                                wire:click="startEdit({{ $item['radDtl'] }})"
                                                class="px-3 py-1 text-xs">
                                                Edit
                                            </x-secondary-button>
                                            <button type="button"
                                                wire:click.prevent="removeRad({{ $item['radDtl'] }})"
                                                wire:confirm="Hapus data radiologi ini?" wire:loading.attr="disabled"
                                                wire:target="removeRad({{ $item['radDtl'] }})"
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
                                Belum ada data radiologi
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($rjRad))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="{{ $isFormLocked ? 2 : 3 }}"
                                class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp {{ number_format(collect($rjRad)->sum('radPrice')) }}
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
