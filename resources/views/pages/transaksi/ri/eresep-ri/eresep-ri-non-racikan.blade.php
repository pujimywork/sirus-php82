<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public int $resepIndex = 0;
    public array $dataDaftarRI = [];
    public array $formEresep = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['eresep-non-racikan-ri'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['eresep-non-racikan-ri']);
        $this->findData($this->riHdrNo);
    }

    /* ===============================
     | FIND DATA
     =============================== */
    protected function findData(?int $riHdrNo): void
    {
        if (empty($riHdrNo)) return;

        if ($this->checkRIStatus($riHdrNo)) {
            $this->isFormLocked = true;
        }

        $data = $this->findDataRI($riHdrNo);

        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data rawat inap tidak ditemukan.');
            return;
        }

        $this->dataDaftarRI = $data;
        $this->dataDaftarRI['eresepHdr'] ??= [];
        $this->dataDaftarRI['eresepHdr'][$this->resepIndex]['eresep'] ??= [];
    }

    /* ===============================
     | SYNC JSON (dipanggil dalam transaksi yang sudah punya lockRIRow)
     =============================== */
    private function syncJson(): void
    {
        $data = $this->findDataRI($this->riHdrNo) ?? [];
        if (empty($data)) throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');

        // Patch hanya eresep di resepIndex ini
        $data['eresepHdr'][$this->resepIndex]['eresep'] =
            $this->dataDaftarRI['eresepHdr'][$this->resepIndex]['eresep'] ?? [];

        $this->updateJsonRI($this->riHdrNo, $data);
        $this->dataDaftarRI = $data;
    }

    /* ===============================
     | LOV SELECTED
     =============================== */
    #[On('lov.selected.eresepRiObatNonRacikan')]
    public function onLovSelected(string $target, array $payload): void
    {
        $this->formEresep = [
            'productId'       => $payload['product_id'],
            'productName'     => $payload['product_name'],
            'jenisKeterangan' => 'NonRacikan',
            'signaX'          => '',
            'signaHari'       => '',
            'qty'             => '',
            'productPrice'    => (float) ($payload['sales_price'] ?? 0),
            'catatanKhusus'   => '',
        ];

        $this->incrementVersion('eresep-non-racikan-ri');
    }

    /* ===============================
     | INSERT PRODUCT
     =============================== */
    public function insertProduct(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        $this->validate(
            [
                'formEresep.productId'    => 'required',
                'formEresep.productName'  => 'required',
                'formEresep.signaX'       => 'required',
                'formEresep.signaHari'    => 'required',
                'formEresep.qty'          => 'required|integer|min:1|max:999',
                'formEresep.productPrice' => 'required|numeric',
                'formEresep.catatanKhusus' => 'nullable|string|max:255',
            ],
            [
                'formEresep.productId.required'   => 'Obat belum dipilih.',
                'formEresep.signaX.required'      => 'Signa wajib diisi.',
                'formEresep.signaHari.required'   => 'Hari wajib diisi.',
                'formEresep.qty.required'         => 'Jumlah wajib diisi.',
                'formEresep.productPrice.required' => 'Harga wajib diisi.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $this->dataDaftarRI['eresepHdr'][$this->resepIndex]['eresep'][] = [
                    'productId'       => $this->formEresep['productId'],
                    'productName'     => $this->formEresep['productName'],
                    'jenisKeterangan' => 'NonRacikan',
                    'signaX'          => $this->formEresep['signaX'],
                    'signaHari'       => $this->formEresep['signaHari'],
                    'qty'             => $this->formEresep['qty'],
                    'productPrice'    => $this->formEresep['productPrice'],
                    'catatanKhusus'   => $this->formEresep['catatanKhusus'] ?? '',
                    'riObatDtl'       => (string) Str::uuid(),
                    'riHdrNo'         => $this->riHdrNo,
                    'resepIndex'      => $this->resepIndex,
                ];

                $this->syncJson();
            });

            $this->reset('formEresep');
            $this->incrementVersion('eresep-non-racikan-ri');
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil ditambahkan.');
            $this->dispatch('eresep-ri.data-updated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | UPDATE PRODUCT (inline edit di tabel)
     =============================== */
    public function updateProduct(string $riObatDtl, mixed $qty, string $signaX, string $signaHari, ?string $catatanKhusus): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        $validator = validator(compact('qty', 'signaX', 'signaHari', 'catatanKhusus'), [
            'qty'           => 'required|integer|min:1|max:999',
            'signaX'        => 'required',
            'signaHari'     => 'required',
            'catatanKhusus' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first());
            return;
        }

        try {
            DB::transaction(function () use ($riObatDtl, $qty, $signaX, $signaHari, $catatanKhusus) {
                $this->lockRIRow($this->riHdrNo);

                foreach ($this->dataDaftarRI['eresepHdr'][$this->resepIndex]['eresep'] as &$item) {
                    if (($item['riObatDtl'] ?? null) === $riObatDtl) {
                        $item['qty']           = $qty;
                        $item['signaX']        = $signaX;
                        $item['signaHari']     = $signaHari;
                        $item['catatanKhusus'] = $catatanKhusus ?? '';
                        break;
                    }
                }
                unset($item);

                $this->syncJson();
            });

            $this->incrementVersion('eresep-non-racikan-ri');
            $this->dispatch('toast', type: 'success', message: 'Obat diperbarui.');
            $this->dispatch('eresep-ri.data-updated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memperbarui: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE PRODUCT
     =============================== */
    public function removeProduct(string $riObatDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($riObatDtl) {
                $this->lockRIRow($this->riHdrNo);

                $obatExists = collect($this->dataDaftarRI['eresepHdr'][$this->resepIndex]['eresep'] ?? [])
                    ->contains('riObatDtl', $riObatDtl);

                if (!$obatExists) {
                    throw new \RuntimeException("Obat tidak ditemukan.");
                }

                $this->dataDaftarRI['eresepHdr'][$this->resepIndex]['eresep'] =
                    collect($this->dataDaftarRI['eresepHdr'][$this->resepIndex]['eresep'] ?? [])
                        ->where('riObatDtl', '!=', $riObatDtl)
                        ->values()
                        ->toArray();

                $this->syncJson();
            });

            $this->incrementVersion('eresep-non-racikan-ri');
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil dihapus.');
            $this->dispatch('eresep-ri.data-updated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET FORM DRAFT
     =============================== */
    public function resetFormEresep(): void
    {
        $this->reset('formEresep');
        $this->incrementVersion('eresep-non-racikan-ri');
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRI = [];
        $this->formEresep   = [];
    }
};
?>

<div>
    <div class="p-2 rounded-lg bg-gray-50">
        <div class="px-4">
            <div wire:key="{{ $this->renderKey('eresep-non-racikan-ri', [$riHdrNo ?? 'new', $resepIndex]) }}">

                <x-input-label :value="__('Non Racikan')" class="pt-2 sm:text-xl" />

                @php
                    $hasTTDResep = !empty($dataDaftarRI['eresepHdr'][$resepIndex]['tandaTanganDokter']['dokterPeresep'] ?? null);
                    $isResepEditable = !$isFormLocked && !$hasTTDResep;
                @endphp

                @role(['Dokter', 'Admin'])
                    @if ($isResepEditable)
                        <div x-data>

                            {{-- LOV Obat --}}
                            @if (!$formEresep)
                                <div class="mt-2"
                                    x-init="$nextTick(() => $el.querySelector('input:not([disabled])')?.focus())">
                                    <livewire:lov.product.lov-product target="eresepRiObatNonRacikan"
                                        label="Nama Obat" :readonly="$isFormLocked" />
                                </div>
                            @endif

                            {{-- Form Input --}}
                            @if ($formEresep)
                                <div class="flex items-end w-full gap-1 mt-2">

                                    {{-- Nama obat (readonly) --}}
                                    <div class="flex-[3]">
                                        <x-input-label :value="__('Nama Obat')" :required="true" />
                                        <x-text-input class="w-full mt-1" :disabled="true"
                                            wire:model="formEresep.productName" />
                                    </div>

                                    {{-- Qty --}}
                                    <div class="flex-[1]">
                                        <x-input-label :value="__('Jml')" :required="true" />
                                        <x-text-input placeholder="Jml" class="w-full mt-1" :disabled="$isFormLocked"
                                            wire:model.live="formEresep.qty" x-ref="qty"
                                            x-init="$nextTick(() => $el.focus())"
                                            x-on:keydown.enter.prevent="$refs.signaX.focus()" />
                                    </div>

                                    {{-- Signa X --}}
                                    <div class="flex-[1]">
                                        <x-input-label :value="__('Signa')" />
                                        <x-text-input placeholder="Signa1" class="w-full mt-1"
                                            :disabled="$isFormLocked" wire:model="formEresep.signaX" x-ref="signaX"
                                            x-on:keydown.enter.prevent="$refs.signaHari.focus()" />
                                    </div>

                                    <div class="pb-2 shrink-0"><span class="text-sm">dd</span></div>

                                    {{-- Signa Hari --}}
                                    <div class="flex-[1]">
                                        <x-input-label :value="__('*')" />
                                        <x-text-input placeholder="Signa2" class="w-full mt-1"
                                            :disabled="$isFormLocked" wire:model="formEresep.signaHari"
                                            x-ref="signaHari"
                                            x-on:keydown.enter.prevent="$refs.catatanKhusus.focus()" />
                                    </div>

                                    {{-- Catatan Khusus --}}
                                    <div class="flex-[3]">
                                        <x-input-label :value="__('Catatan Khusus')" />
                                        <x-text-input placeholder="Catatan Khusus" class="w-full mt-1"
                                            :disabled="$isFormLocked" wire:model="formEresep.catatanKhusus"
                                            x-ref="catatanKhusus"
                                            x-on:keydown.enter.prevent="$wire.insertProduct()" />
                                    </div>

                                    {{-- Hapus draft --}}
                                    <div class="ml-auto shrink-0">
                                        <x-input-label value="" />
                                        <x-secondary-button class="inline-flex mt-1"
                                            wire:click="resetFormEresep">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 18 20">
                                                <path
                                                    d="M17 4h-4V2a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v2H1a1 1 0 0 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6h1a1 1 0 1 0 0-2ZM7 2h4v2H7V2Zm1 14a1 1 0 1 1-2 0V8a1 1 0 0 1 2 0v8Z" />
                                            </svg>
                                        </x-secondary-button>
                                    </div>
                                </div>

                                {{-- Errors --}}
                                <div class="flex w-full gap-1 text-xs">
                                    <div class="flex-[3]">
                                        <x-input-error :messages="$errors->get('formEresep.productName')" />
                                    </div>
                                    <div class="flex-[1]">
                                        <x-input-error :messages="$errors->get('formEresep.qty')" />
                                    </div>
                                    <div class="flex-[1]">
                                        <x-input-error :messages="$errors->get('formEresep.signaX')" />
                                    </div>
                                    <div class="shrink-0"></div>
                                    <div class="flex-[1]">
                                        <x-input-error :messages="$errors->get('formEresep.signaHari')" />
                                    </div>
                                    <div class="flex-[3]">
                                        <x-input-error :messages="$errors->get('formEresep.catatanKhusus')" />
                                    </div>
                                    <div class="ml-auto shrink-0"></div>
                                </div>
                            @endif

                        </div>
                    @endif
                @endrole

                {{-- Tabel Obat Non Racikan --}}
                <div class="flex flex-col my-2">
                    <div class="overflow-x-auto rounded-lg">
                        <div class="inline-block min-w-full align-middle">
                            <div class="overflow-hidden shadow sm:rounded-lg">
                                <table class="w-full text-sm text-left text-gray-500 table-auto dark:text-gray-400">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                        <tr>
                                            <th class="w-28 px-4 py-3">Jenis</th>
                                            <th class="px-4 py-3">Nama Obat</th>
                                            <th class="w-20 px-4 py-3">Jumlah</th>
                                            <th class="px-4 py-3">Signa</th>
                                            <th class="w-8 px-4 py-3 text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white">
                                        @foreach ($dataDaftarRI['eresepHdr'][$resepIndex]['eresep'] ?? [] as $key => $eresep)
                                            <tr class="border-b group" x-data>
                                                <td class="w-28 px-4 py-3 whitespace-nowrap">
                                                    {{ $eresep['jenisKeterangan'] ?? 'NonRacikan' }}
                                                </td>
                                                <td class="px-4 py-3">{{ $eresep['productName'] }}</td>
                                                <td class="w-20 px-4 py-3">
                                                    <x-text-input placeholder="Jml" :disabled="!$isResepEditable"
                                                        wire:model="dataDaftarRI.eresepHdr.{{ $resepIndex }}.eresep.{{ $key }}.qty"
                                                        x-ref="qty{{ $key }}"
                                                        x-on:keydown.enter.prevent="$refs.signaX{{ $key }}.focus()" />
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-1">
                                                        <div class="w-16 shrink-0">
                                                            <x-text-input placeholder="Signa1"
                                                                :disabled="!$isResepEditable"
                                                                wire:model="dataDaftarRI.eresepHdr.{{ $resepIndex }}.eresep.{{ $key }}.signaX"
                                                                x-ref="signaX{{ $key }}"
                                                                x-on:keydown.enter.prevent="$refs.signaHari{{ $key }}.focus()" />
                                                        </div>
                                                        <span class="text-sm text-gray-500 shrink-0">dd</span>
                                                        <div class="w-16 shrink-0">
                                                            <x-text-input placeholder="Signa2"
                                                                :disabled="!$isResepEditable"
                                                                wire:model="dataDaftarRI.eresepHdr.{{ $resepIndex }}.eresep.{{ $key }}.signaHari"
                                                                x-ref="signaHari{{ $key }}"
                                                                x-on:keydown.enter.prevent="$refs.catatanKhusus{{ $key }}.focus()" />
                                                        </div>
                                                        <div class="flex-1">
                                                            <x-text-input placeholder="Catatan Khusus"
                                                                :disabled="!$isResepEditable"
                                                                wire:model="dataDaftarRI.eresepHdr.{{ $resepIndex }}.eresep.{{ $key }}.catatanKhusus"
                                                                x-ref="catatanKhusus{{ $key }}"
                                                                x-on:keydown.enter.prevent="
                                                                    $wire.updateProduct(
                                                                        '{{ $eresep['riObatDtl'] }}',
                                                                        $wire.dataDaftarRI.eresepHdr[{{ $resepIndex }}].eresep[{{ $key }}].qty,
                                                                        $wire.dataDaftarRI.eresepHdr[{{ $resepIndex }}].eresep[{{ $key }}].signaX,
                                                                        $wire.dataDaftarRI.eresepHdr[{{ $resepIndex }}].eresep[{{ $key }}].signaHari,
                                                                        $wire.dataDaftarRI.eresepHdr[{{ $resepIndex }}].eresep[{{ $key }}].catatanKhusus
                                                                    );
                                                                    $nextTick(() => $refs.qty{{ $key }}.focus())
                                                                " />
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="w-8 px-4 py-3 text-center">
                                                    @role(['Dokter', 'Admin'])
                                                        @if ($isResepEditable)
                                                            <x-secondary-button class="inline-flex"
                                                                wire:click="removeProduct('{{ $eresep['riObatDtl'] }}')">
                                                                <svg class="w-5 h-5" fill="currentColor"
                                                                    viewBox="0 0 18 20">
                                                                    <path
                                                                        d="M17 4h-4V2a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v2H1a1 1 0 0 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6h1a1 1 0 1 0 0-2ZM7 2h4v2H7V2Zm1 14a1 1 0 1 1-2 0V8a1 1 0 0 1 2 0v8Z" />
                                                                </svg>
                                                            </x-secondary-button>
                                                        @endif
                                                    @endrole
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>{{-- end wire:key --}}
        </div>
    </div>
</div>
