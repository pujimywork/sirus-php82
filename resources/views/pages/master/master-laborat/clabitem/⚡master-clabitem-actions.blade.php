<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode      = 'create';
    public array  $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public array $formClabitem = [
        'clabitem_id'    => '',
        'clabitem_desc'  => '',
        'clab_id'        => '',
        'product_id'     => '',
        'is_group'       => '',
        'clabitem_group' => '',
        'price'          => '0',
        'dosage'         => '0',
        'unit_desc'      => '',
        'item_seq'       => '',
        'item_code'      => '',
        'normal_m'       => '',
        'normal_f'       => '',
        'lowhigh_status' => '',
        'hidden_status'  => '',
        'low_limit_m'    => '',
        'high_limit_m'   => '',
        'low_limit_f'    => '',
        'high_limit_f'   => '',
        'low_limit_k'    => '',
        'high_limit_k'   => '',
        'unit_convert'   => '',
        'loinc_code'     => '',
        'loinc_display'  => '',
        'status'         => '',
    ];

    /* --- Composite PK lama (untuk edit) --- */
    private string $oldClabitemId = '';
    private string $oldClabId     = '';
    private string $oldProductId  = '';

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    // =========================================================
    // CLABITEM
    // =========================================================

    #[On('master.laborat.openCreateClabitem')]
    public function openCreateClabitem(string $clabId): void
    {
        $this->resetAll();
        $this->formMode = 'create';
        $this->formClabitem['clab_id'] = $clabId;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-laborat-clabitem');
        $this->dispatch('focus-clabitem-id');
    }

    #[On('master.laborat.openEditClabitem')]
    public function openEditClabitem(string $clabitemId, string $clabId, string $productId): void
    {
        $row = DB::table('lbmst_clabitems')
            ->where('clabitem_id', $clabitemId)
            ->where('clab_id', $clabId)
            ->where('product_id', $productId)
            ->first();

        if (! $row) {
            return;
        }

        $this->resetAll();
        $this->formMode = 'edit';
        $this->oldClabitemId = $clabitemId;
        $this->oldClabId     = $clabId;
        $this->oldProductId  = $productId;

        $this->formClabitem = [
            'clabitem_id'    => (string) $row->clabitem_id,
            'clabitem_desc'  => (string) ($row->clabitem_desc ?? ''),
            'clab_id'        => (string) $row->clab_id,
            'product_id'     => (string) $row->product_id,
            'is_group'       => (string) ($row->is_group ?? ''),
            'clabitem_group' => (string) ($row->clabitem_group ?? ''),
            'price'          => (string) ($row->price ?? '0'),
            'dosage'         => (string) ($row->dosage ?? '0'),
            'unit_desc'      => (string) ($row->unit_desc ?? ''),
            'item_seq'       => (string) ($row->item_seq ?? ''),
            'item_code'      => (string) ($row->item_code ?? ''),
            'normal_m'       => (string) ($row->normal_m ?? ''),
            'normal_f'       => (string) ($row->normal_f ?? ''),
            'lowhigh_status' => (string) ($row->lowhigh_status ?? ''),
            'hidden_status'  => (string) ($row->hidden_status ?? ''),
            'low_limit_m'    => (string) ($row->low_limit_m ?? ''),
            'high_limit_m'   => (string) ($row->high_limit_m ?? ''),
            'low_limit_f'    => (string) ($row->low_limit_f ?? ''),
            'high_limit_f'   => (string) ($row->high_limit_f ?? ''),
            'low_limit_k'    => (string) ($row->low_limit_k ?? ''),
            'high_limit_k'   => (string) ($row->high_limit_k ?? ''),
            'unit_convert'   => (string) ($row->unit_convert ?? ''),
            'loinc_code'     => (string) ($row->loinc_code ?? ''),
            'loinc_display'  => (string) ($row->loinc_display ?? ''),
            'status'         => (string) ($row->status ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-laborat-clabitem');
        $this->dispatch('focus-clabitem-desc');
    }

    #[On('master.laborat.deleteClabitem')]
    public function deleteClabitem(string $clabitemId, string $clabId, string $productId): void
    {
        try {
            $deleted = DB::table('lbmst_clabitems')
                ->where('clabitem_id', $clabitemId)
                ->where('clab_id', $clabId)
                ->where('product_id', $productId)
                ->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data pemeriksaan tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Pemeriksaan berhasil dihapus.');
            $this->dispatch('master.laborat.saved', entity: 'clabitem');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Pemeriksaan tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $this->validate(
            [
                'formClabitem.clabitem_id'    => 'required|string|max:10',
                'formClabitem.clabitem_desc'  => 'required|string|max:100',
                'formClabitem.clab_id'        => 'required|string|max:5',
                'formClabitem.product_id'     => 'required|string|max:10',
                'formClabitem.is_group'       => 'nullable|string|max:10',
                'formClabitem.clabitem_group' => 'nullable|string|max:10',
                'formClabitem.price'          => 'nullable|integer|min:0',
                'formClabitem.dosage'         => 'nullable|integer|min:0',
                'formClabitem.unit_desc'      => 'nullable|string|max:50',
                'formClabitem.item_seq'       => 'nullable|numeric|min:0',
                'formClabitem.item_code'      => 'nullable|string|max:25',
                'formClabitem.normal_m'       => 'nullable|string|max:100',
                'formClabitem.normal_f'       => 'nullable|string|max:100',
                'formClabitem.lowhigh_status' => 'nullable|string|max:3',
                'formClabitem.hidden_status'  => 'nullable|string|max:3',
                'formClabitem.low_limit_m'    => 'nullable|numeric',
                'formClabitem.high_limit_m'   => 'nullable|numeric',
                'formClabitem.low_limit_f'    => 'nullable|numeric',
                'formClabitem.high_limit_f'   => 'nullable|numeric',
                'formClabitem.low_limit_k'    => 'nullable|numeric',
                'formClabitem.high_limit_k'   => 'nullable|numeric',
                'formClabitem.unit_convert'   => 'nullable|numeric',
                'formClabitem.loinc_code'     => 'nullable|string|max:20',
                'formClabitem.loinc_display'  => 'nullable|string|max:200',
                'formClabitem.status'         => 'nullable|string|max:10',
            ],
            [],
            [
                'formClabitem.clabitem_id'    => 'Kode Pemeriksaan',
                'formClabitem.clabitem_desc'  => 'Nama Pemeriksaan',
                'formClabitem.clab_id'        => 'Kategori Lab',
                'formClabitem.product_id'     => 'Kode Produk',
                'formClabitem.price'          => 'Tarif Pemeriksaan',
                'formClabitem.unit_desc'      => 'Satuan Hasil',
            ],
        );

        $payload = [
            'clabitem_desc'  => $this->formClabitem['clabitem_desc'],
            'is_group'       => $this->formClabitem['is_group'] ?: null,
            'clabitem_group' => $this->formClabitem['clabitem_group'] ?: null,
            'price'          => (int) ($this->formClabitem['price'] ?: 0),
            'dosage'         => (int) ($this->formClabitem['dosage'] ?: 0),
            'unit_desc'      => $this->formClabitem['unit_desc'] ?: null,
            'item_seq'       => $this->formClabitem['item_seq'] !== '' ? (int) $this->formClabitem['item_seq'] : null,
            'item_code'      => $this->formClabitem['item_code'] ?: null,
            'normal_m'       => $this->formClabitem['normal_m'] ?: null,
            'normal_f'       => $this->formClabitem['normal_f'] ?: null,
            'lowhigh_status' => in_array($this->formClabitem['lowhigh_status'], ['Y', '1']) ? 'Y' : null,
            'hidden_status'  => $this->formClabitem['hidden_status'] ?: null,
            'low_limit_m'    => $this->formClabitem['low_limit_m'] !== '' ? (float) $this->formClabitem['low_limit_m'] : null,
            'high_limit_m'   => $this->formClabitem['high_limit_m'] !== '' ? (float) $this->formClabitem['high_limit_m'] : null,
            'low_limit_f'    => $this->formClabitem['low_limit_f'] !== '' ? (float) $this->formClabitem['low_limit_f'] : null,
            'high_limit_f'   => $this->formClabitem['high_limit_f'] !== '' ? (float) $this->formClabitem['high_limit_f'] : null,
            'low_limit_k'    => $this->formClabitem['low_limit_k'] !== '' ? (float) $this->formClabitem['low_limit_k'] : null,
            'high_limit_k'   => $this->formClabitem['high_limit_k'] !== '' ? (float) $this->formClabitem['high_limit_k'] : null,
            'unit_convert'   => $this->formClabitem['unit_convert'] !== '' ? (float) $this->formClabitem['unit_convert'] : null,
            'loinc_code'     => $this->formClabitem['loinc_code'] ?: null,
            'loinc_display'  => $this->formClabitem['loinc_display'] ?: null,
            'status'         => $this->formClabitem['status'] ?: null,
        ];

        if ($this->formMode === 'create') {
            DB::table('lbmst_clabitems')->insert([
                'clabitem_id' => $this->formClabitem['clabitem_id'],
                'clab_id'     => $this->formClabitem['clab_id'],
                'product_id'  => $this->formClabitem['product_id'],
                ...$payload,
            ]);
        } else {
            DB::table('lbmst_clabitems')
                ->where('clabitem_id', $this->oldClabitemId)
                ->where('clab_id', $this->oldClabId)
                ->where('product_id', $this->oldProductId)
                ->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data pemeriksaan berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.laborat.saved', entity: 'clabitem');
    }

    /* =========================================================
     * LOV — Grup Induk
     * ========================================================= */
    #[On('lov.selected.clabitemGroup')]
    public function onClabitemGroupSelected(string $target, ?array $payload): void
    {
        $this->formClabitem['clabitem_group'] = $payload['clabitem_id'] ?? '';
    }

    /* =========================================================
     * LOV — LOINC (Satu Sehat)
     * ========================================================= */
    #[On('lov.selected.loincLab')]
    public function onLoincLabSelected(string $target, ?array $payload): void
    {
        $this->formClabitem['loinc_code']    = $payload['loinc_code'] ?? '';
        $this->formClabitem['loinc_display'] = $payload['display'] ?? '';
    }

    #[On('lov.cleared.loincLab')]
    public function onLoincLabCleared(string $target): void
    {
        $this->formClabitem['loinc_code']    = '';
        $this->formClabitem['loinc_display'] = '';
    }

    public function closeModal(): void
    {
        $this->resetAll();
        $this->dispatch('close-modal', name: 'master-laborat-clabitem');
        $this->resetVersion();
    }

    private function resetAll(): void
    {
        $this->formClabitem = [
            'clabitem_id' => '', 'clabitem_desc' => '', 'clab_id' => '', 'product_id' => '',
            'is_group' => '', 'clabitem_group' => '', 'price' => '0', 'dosage' => '0',
            'unit_desc' => '', 'item_seq' => '', 'item_code' => '', 'normal_m' => '',
            'normal_f' => '', 'lowhigh_status' => '', 'hidden_status' => '',
            'low_limit_m' => '', 'high_limit_m' => '', 'low_limit_f' => '', 'high_limit_f' => '',
            'low_limit_k' => '', 'high_limit_k' => '', 'unit_convert' => '',
            'loinc_code' => '', 'loinc_display' => '', 'status' => '',
        ];
        $this->oldClabitemId = '';
        $this->oldClabId     = '';
        $this->oldProductId  = '';
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-laborat-clabitem" size="full" height="full" focusable>
        <div class="flex flex-col min-h-0"
            wire:key="{{ $this->renderKey('modal', [$formMode]) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;"></div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo" class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo" class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah' : 'Tambah' }} Pemeriksaan Lab
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Lengkapi data pemeriksaan laboratorium lalu klik Simpan.</p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
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
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20 max-h-[calc(100dvh-300px)]"
                x-data
                x-on:focus-clabitem-id.window="$nextTick(() => setTimeout(() => $refs.inputClabitemId?.focus(), 150))"
                x-on:focus-clabitem-desc.window="$nextTick(() => setTimeout(() => $refs.inputClabitemDesc?.focus(), 150))">

                {{-- ═══ SECTION 1: Pemeriksaan ═══ --}}
                <x-border-form title="Pemeriksaan" class="mb-4">
                    <div class="space-y-4">

                        {{-- Kode Pemeriksaan + Nama Pemeriksaan + Paket? --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-6 items-start">
                            <div>
                                <x-input-label value="Kode Pemeriksaan" />
                                <x-text-input wire:model.live="formClabitem.clabitem_id" x-ref="inputClabitemId"
                                    :disabled="$formMode === 'edit'" maxlength="10" :error="$errors->has('formClabitem.clabitem_id')"
                                    class="w-full mt-1 uppercase"
                                    x-on:keydown.enter.prevent="$refs.inputClabitemDesc?.focus()" />
                                <x-input-error :messages="$errors->get('formClabitem.clabitem_id')" class="mt-1" />
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Kode unik pemeriksaan</p>
                            </div>
                            <div class="sm:col-span-4">
                                <x-input-label value="Nama Pemeriksaan" />
                                <x-text-input wire:model.live="formClabitem.clabitem_desc" x-ref="inputClabitemDesc"
                                    maxlength="100" :error="$errors->has('formClabitem.clabitem_desc')" class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputIsGroup?.focus()" />
                                <x-input-error :messages="$errors->get('formClabitem.clabitem_desc')" class="mt-1" />
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Nama yang tampil di hasil lab pasien</p>
                            </div>
                            <div>
                                <x-input-label value="Bagian dari Paket?" />
                                <x-select-input wire:model.live="formClabitem.is_group" x-ref="inputIsGroup" class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputUnitDesc?.focus()">
                                    <option value="">Tidak</option>
                                    <option value="1">Ya</option>
                                </x-select-input>
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Jika Ya, pilih paket induk di bawah. Item otomatis ikut saat paket induk dipilih di transaksi</p>
                            </div>
                        </div>

                        {{-- Paket Induk LOV (muncul jika Ya - bagian dari paket) --}}
                        @if ($formClabitem['is_group'] === '1')
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 items-start">
                            <div>
                                <livewire:lov.clabitem-group.lov-clabitem-group
                                    target="clabitemGroup"
                                    label="Paket Induk"
                                    placeholder="Ketik nama paket pemeriksaan..."
                                    :clabId="$formClabitem['clab_id']"
                                    :initialClabitemId="$formClabitem['clabitem_group'] ?: null"
                                    :disabled="false"
                                    wire:key="lov-clabgrp-{{ $formClabitem['clab_id'] }}-{{ $renderVersions['modal'] ?? 0 }}"
                                />
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Pilih paket induk yang menaungi pemeriksaan ini</p>
                            </div>
                        </div>
                        @endif

                    </div>
                </x-border-form>

                {{-- ═══ SECTION 2: Nilai Rujukan ═══ --}}
                <x-border-form title="Nilai Rujukan" class="mb-4">
                    <div class="space-y-4">

                        {{-- Satuan Hasil, Volume Reagen, Kode Analyzer, LOINC, Jenis Nilai Rujukan --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Satuan Hasil" />
                                <x-text-input wire:model.live="formClabitem.unit_desc" x-ref="inputUnitDesc"
                                    maxlength="50" :error="$errors->has('formClabitem.unit_desc')" class="w-full mt-1"
                                    placeholder="g/dL, mg/dL, %"
                                    x-on:keydown.enter.prevent="$refs.inputDosage?.focus()" />
                                <x-input-error :messages="$errors->get('formClabitem.unit_desc')" class="mt-1" />
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Tampil di belakang angka hasil, misal: 12.5 <strong>g/dL</strong></p>
                            </div>
                            <div>
                                <x-input-label value="Volume Reagen" />
                                <x-text-input-number wire:model="formClabitem.dosage" x-ref="inputDosage"
                                    :error="$errors->has('formClabitem.dosage')" class="mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputItemCode?.focus()" />
                                <x-input-error :messages="$errors->get('formClabitem.dosage')" class="mt-1" />
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Kebutuhan reagen per pemeriksaan, untuk perhitungan stok</p>
                            </div>
                            <div>
                                <x-input-label value="Jenis Nilai Rujukan" />
                                <x-select-input wire:model.live="formClabitem.lowhigh_status" x-ref="inputLowHighStatus" class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputNormalM?.focus() || $refs.inputLowM?.focus()">
                                    <option value="">Teks Deskriptif</option>
                                    <option value="Y">Rentang Angka (Low-High)</option>
                                </x-select-input>
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Rentang Angka: sistem hitung Tinggi/Rendah otomatis. Teks: untuk hasil seperti NEGATIF/POSITIF</p>
                            </div>
                        </div>

                        {{-- Mapping Alat & Satu Sehat --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Kode Analyzer" />
                                <x-text-input wire:model.live="formClabitem.item_code" x-ref="inputItemCode"
                                    maxlength="25" :error="$errors->has('formClabitem.item_code')"
                                    class="w-full mt-1" placeholder="HGB, WBC, RBC..."
                                    x-on:keydown.enter.prevent="$refs.inputLoincCode?.focus()" />
                                <x-input-error :messages="$errors->get('formClabitem.item_code')" class="mt-1" />
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Kode dari alat (Mindray dll) untuk import hasil otomatis</p>
                            </div>
                            <div class="sm:col-span-2">
                                <livewire:lov.loinc.lov-loinc
                                    target="loincLab"
                                    label="Kode LOINC (Satu Sehat)"
                                    placeholder="Ketik nama pemeriksaan / kode LOINC..."
                                    :initialLoincCode="$formClabitem['loinc_code'] ?: null"
                                    :disabled="false"
                                    wire:key="lov-loinc-{{ $renderVersions['modal'] ?? 0 }}"
                                />
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Cari kode LOINC dari database lokal atau FHIR server. Dipakai untuk kirim ke Satu Sehat</p>
                            </div>
                        </div>

                        @if ($formClabitem['lowhigh_status'] === 'Y' || $formClabitem['lowhigh_status'] === '1')
                            {{-- Rentang Angka --}}
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label value="Pria (Batas Bawah - Atas)" />
                                    <div class="flex items-center gap-2 mt-1">
                                        <x-text-input wire:model.live="formClabitem.low_limit_m" x-ref="inputLowM" type="number" step="0.01" class="w-full" placeholder="Bawah"
                                            x-on:keydown.enter.prevent="$refs.inputHighM?.focus()" />
                                        <span class="text-gray-400">-</span>
                                        <x-text-input wire:model.live="formClabitem.high_limit_m" x-ref="inputHighM" type="number" step="0.01" class="w-full" placeholder="Atas"
                                            x-on:keydown.enter.prevent="$refs.inputLowF?.focus()" />
                                    </div>
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Hasil di luar rentang ini ditandai Tinggi/Rendah</p>
                                </div>
                                <div>
                                    <x-input-label value="Wanita (Batas Bawah - Atas)" />
                                    <div class="flex items-center gap-2 mt-1">
                                        <x-text-input wire:model.live="formClabitem.low_limit_f" x-ref="inputLowF" type="number" step="0.01" class="w-full" placeholder="Bawah"
                                            x-on:keydown.enter.prevent="$refs.inputHighF?.focus()" />
                                        <span class="text-gray-400">-</span>
                                        <x-text-input wire:model.live="formClabitem.high_limit_f" x-ref="inputHighF" type="number" step="0.01" class="w-full" placeholder="Atas"
                                            x-on:keydown.enter.prevent="$refs.inputLowK?.focus()" />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Anak (Batas Bawah - Atas)" />
                                    <div class="flex items-center gap-2 mt-1">
                                        <x-text-input wire:model.live="formClabitem.low_limit_k" x-ref="inputLowK" type="number" step="0.01" class="w-full" placeholder="Bawah"
                                            x-on:keydown.enter.prevent="$refs.inputHighK?.focus()" />
                                        <span class="text-gray-400">-</span>
                                        <x-text-input wire:model.live="formClabitem.high_limit_k" x-ref="inputHighK" type="number" step="0.01" class="w-full" placeholder="Atas"
                                            x-on:keydown.enter.prevent="$refs.inputUnitConvert?.focus()" />
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                                <div>
                                    <x-input-label value="Faktor Konversi" />
                                    <x-text-input wire:model.live="formClabitem.unit_convert" x-ref="inputUnitConvert" type="number" step="0.01" class="w-full mt-1"
                                        x-on:keydown.enter.prevent="$refs.inputPrice?.focus()" />
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Hasil dari alat dibagi nilai ini saat simpan, dikali kembali saat tampil. Contoh: HGB dari Mindray /10</p>
                                </div>
                            </div>
                        @else
                            {{-- Teks Deskriptif --}}
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="Rujukan Pria" />
                                    <x-text-input wire:model.live="formClabitem.normal_m" x-ref="inputNormalM"
                                        maxlength="100" class="w-full mt-1" placeholder="Contoh: 13 - 18 g/dL atau NEGATIF"
                                        x-on:keydown.enter.prevent="$refs.inputNormalF?.focus()" />
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Tampil di kolom Nilai Rujukan pada hasil lab pasien pria</p>
                                </div>
                                <div>
                                    <x-input-label value="Rujukan Wanita" />
                                    <x-text-input wire:model.live="formClabitem.normal_f" x-ref="inputNormalF"
                                        maxlength="100" class="w-full mt-1" placeholder="Contoh: 12 - 16 g/dL atau NEGATIF"
                                        x-on:keydown.enter.prevent="$refs.inputPrice?.focus()" />
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Tampil di kolom Nilai Rujukan pada hasil lab pasien wanita</p>
                                </div>
                            </div>
                        @endif

                    </div>
                </x-border-form>

                {{-- ═══ SECTION 3: Tarif & Konfigurasi ═══ --}}
                <x-border-form title="Tarif & Konfigurasi">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div>
                            <x-input-label value="Tarif" />
                            <x-text-input-number wire:model="formClabitem.price" x-ref="inputPrice"
                                :error="$errors->has('formClabitem.price')" class="mt-1"
                                x-on:keydown.enter.prevent="$refs.inputItemSeq?.focus()" />
                            <x-input-error :messages="$errors->get('formClabitem.price')" class="mt-1" />
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Biaya tagihan pasien</p>
                        </div>
                        <div>
                            <x-input-label value="Urutan" />
                            <x-text-input-number wire:model="formClabitem.item_seq" x-ref="inputItemSeq"
                                :error="$errors->has('formClabitem.item_seq')" class="mt-1"
                                x-on:keydown.enter.prevent="$refs.inputStatus?.focus()" />
                            <x-input-error :messages="$errors->get('formClabitem.item_seq')" class="mt-1" />
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Posisi di cetakan</p>
                        </div>
                        <div>
                            <x-input-label value="Status" />
                            <x-select-input wire:model.live="formClabitem.status" x-ref="inputStatus" class="w-full mt-1"
                                x-on:keydown.enter.prevent="$refs.inputHiddenStatus?.focus()">
                                <option value="">Aktif</option>
                                <option value="0">Non Aktif</option>
                            </x-select-input>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Non Aktif = tidak bisa dipilih</p>
                        </div>
                        <div>
                            <x-input-label value="Tampil di Hasil" />
                            <x-select-input wire:model.live="formClabitem.hidden_status" x-ref="inputHiddenStatus" class="w-full mt-1"
                                x-on:keydown.enter.prevent="$wire.save()">
                                <option value="">Ya</option>
                                <option value="1">Sembunyikan</option>
                            </x-select-input>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Sembunyi = tidak di cetakan</p>
                        </div>
                    </div>
                </x-border-form>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-gray-100 border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">untuk berpindah field</span>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
