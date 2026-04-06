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
    public array $formEresepRacikan = [];
    public string $noRacikan = 'R1';

    public array $renderVersions = [];
    protected array $renderAreas = ['eresep-racikan-ri'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['eresep-racikan-ri']);
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
        $this->dataDaftarRI['eresepHdr'][$this->resepIndex]['eresepRacikan'] ??= [];
    }

    /* ===============================
     | SYNC JSON (dipanggil dalam transaksi yang sudah punya lockRIRow)
     =============================== */
    private function syncJson(): void
    {
        $data = $this->findDataRI($this->riHdrNo) ?? [];
        if (empty($data)) throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');

        // Patch hanya eresepRacikan di resepIndex ini
        $data['eresepHdr'][$this->resepIndex]['eresepRacikan'] =
            $this->dataDaftarRI['eresepHdr'][$this->resepIndex]['eresepRacikan'] ?? [];

        $this->updateJsonRI($this->riHdrNo, $data);
        $this->dataDaftarRI = $data;
    }

    /* ===============================
     | LOV SELECTED
     =============================== */
    #[On('lov.selected.eresepRiObatRacikan')]
    public function onLovSelected(string $target, array $payload): void
    {
        $this->formEresepRacikan = [
            'productId'       => $payload['product_id'],
            'productName'     => $payload['product_name'],
            'jenisKeterangan' => 'Racikan',
            'noRacikan'       => $this->noRacikan,
            'sedia'           => 1,
            'dosis'           => '',
            'qty'             => '',
            'catatan'         => '',
            'catatanKhusus'   => '',
            'signaX'          => 1,
            'signaHari'       => 1,
            'productPrice'    => (float) ($payload['sales_price'] ?? 0),
        ];

        $this->incrementVersion('eresep-racikan-ri');
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
                'formEresepRacikan.productName'  => 'required',
                'formEresepRacikan.dosis'        => 'required|max:150',
                'formEresepRacikan.sedia'        => 'required',
                'formEresepRacikan.qty'          => 'nullable|integer|digits_between:1,3',
                'formEresepRacikan.catatan'      => 'nullable|max:150',
                'formEresepRacikan.catatanKhusus' => 'nullable|max:150',
            ],
            [
                'formEresepRacikan.productName.required' => 'Nama obat wajib diisi.',
                'formEresepRacikan.dosis.required'       => 'Dosis wajib diisi.',
                'formEresepRacikan.sedia.required'       => 'Sediaan wajib diisi.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $this->dataDaftarRI['eresepHdr'][$this->resepIndex]['eresepRacikan'][] = [
                    'noRacikan'       => $this->formEresepRacikan['noRacikan'],
                    'productId'       => $this->formEresepRacikan['productId'],
                    'productName'     => $this->formEresepRacikan['productName'],
                    'jenisKeterangan' => 'Racikan',
                    'sedia'           => $this->formEresepRacikan['sedia'],
                    'dosis'           => $this->formEresepRacikan['dosis'],
                    'qty'             => $this->formEresepRacikan['qty'] ?? '',
                    'catatan'         => $this->formEresepRacikan['catatan'] ?? '',
                    'catatanKhusus'   => $this->formEresepRacikan['catatanKhusus'] ?? '',
                    'signaX'          => $this->formEresepRacikan['signaX'],
                    'signaHari'       => $this->formEresepRacikan['signaHari'],
                    'productPrice'    => $this->formEresepRacikan['productPrice'] ?? 0,
                    'riObatDtl'       => (string) Str::uuid(),
                    'riHdrNo'         => $this->riHdrNo,
                    'resepIndex'      => $this->resepIndex,
                ];

                $this->syncJson();
            });

            $this->reset('formEresepRacikan');
            $this->incrementVersion('eresep-racikan-ri');
            $this->dispatch('toast', type: 'success', message: 'Obat racikan berhasil ditambahkan.');
            $this->dispatch('eresep-ri.data-updated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | UPDATE PRODUCT (inline edit)
     =============================== */
    public function updateProduct(string $riObatDtl, mixed $qty, string $dosis, ?string $catatan, ?string $catatanKhusus): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        $validator = validator(compact('qty', 'dosis', 'catatan', 'catatanKhusus'), [
            'dosis'          => 'required|max:150',
            'qty'            => 'nullable|integer|digits_between:1,3',
            'catatan'        => 'nullable|max:150',
            'catatanKhusus'  => 'nullable|max:150',
        ]);

        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first());
            return;
        }

        try {
            DB::transaction(function () use ($riObatDtl, $qty, $dosis, $catatan, $catatanKhusus) {
                $this->lockRIRow($this->riHdrNo);

                foreach ($this->dataDaftarRI['eresepHdr'][$this->resepIndex]['eresepRacikan'] as &$item) {
                    if (($item['riObatDtl'] ?? null) === $riObatDtl) {
                        $item['qty']           = $qty;
                        $item['dosis']         = $dosis;
                        $item['catatan']       = $catatan ?? '';
                        $item['catatanKhusus'] = $catatanKhusus ?? '';
                        break;
                    }
                }
                unset($item);

                $this->syncJson();
            });

            $this->incrementVersion('eresep-racikan-ri');
            $this->dispatch('toast', type: 'success', message: 'Obat racikan diperbarui.');
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

                $obatExists = collect($this->dataDaftarRI['eresepHdr'][$this->resepIndex]['eresepRacikan'] ?? [])
                    ->contains('riObatDtl', $riObatDtl);

                if (!$obatExists) {
                    throw new \RuntimeException("Obat racikan tidak ditemukan.");
                }

                $this->dataDaftarRI['eresepHdr'][$this->resepIndex]['eresepRacikan'] =
                    collect($this->dataDaftarRI['eresepHdr'][$this->resepIndex]['eresepRacikan'] ?? [])
                        ->where('riObatDtl', '!=', $riObatDtl)
                        ->values()
                        ->toArray();

                $this->syncJson();
            });

            $this->incrementVersion('eresep-racikan-ri');
            $this->dispatch('toast', type: 'success', message: 'Obat racikan berhasil dihapus.');
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
    public function resetFormEresepRacikan(): void
    {
        $this->reset('formEresepRacikan');
        $this->incrementVersion('eresep-racikan-ri');
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked  = false;
        $this->dataDaftarRI  = [];
        $this->formEresepRacikan = [];
        $this->noRacikan = 'R1';
    }
};
?>

<div>
    <div class="p-2 rounded-lg bg-gray-50">
        <div class="px-4">
            <div wire:key="{{ $this->renderKey('eresep-racikan-ri', [$riHdrNo ?? 'new', $resepIndex]) }}">

                <x-input-label :value="__('Racikan')" class="pt-2 sm:text-xl" />

                @php
                    $hasTTDResep = !empty($dataDaftarRI['eresepHdr'][$resepIndex]['tandaTanganDokter']['dokterPeresep'] ?? null);
                    $isResepEditable = !$isFormLocked && !$hasTTDResep;
                @endphp

                @role(['Dokter', 'Admin'])
                    @if ($isResepEditable)
                        <div x-data>

                            {{-- LOV + No Racikan --}}
                            @if (!$formEresepRacikan)
                                <div class="flex items-center gap-3 mt-2"
                                    x-init="$nextTick(() => $el.querySelector('input:not([disabled])')?.focus())">
                                    <div class="flex-1">
                                        <livewire:lov.product.lov-product target="eresepRiObatRacikan"
                                            label="Nama Obat Racikan" :readonly="$isFormLocked" />
                                    </div>
                                    <div class="w-32">
                                        <x-input-label :value="__('No Racikan')" />
                                        <x-text-input wire:model="noRacikan" placeholder="R1"
                                            :disabled="$isFormLocked" class="mt-1" />
                                    </div>
                                </div>
                            @endif

                            {{-- Form Input --}}
                            @if ($formEresepRacikan)
                                <div class="flex items-end w-full gap-1 mt-2">

                                    {{-- No Racikan --}}
                                    <div class="flex-[1]">
                                        <x-input-label :value="__('Racikan')" />
                                        <x-text-input class="w-full mt-1"
                                            wire:model="formEresepRacikan.noRacikan" />
                                    </div>

                                    {{-- Nama obat (readonly) --}}
                                    <div class="flex-[3]">
                                        <x-input-label :value="__('Nama Obat')" :required="true" />
                                        <x-text-input class="w-full mt-1" :disabled="true"
                                            wire:model="formEresepRacikan.productName" />
                                    </div>

                                    {{-- Sedia --}}
                                    <div class="flex-[1]">
                                        <x-input-label :value="__('Sedia')" :required="true" />
                                        <x-text-input placeholder="Sedia" class="w-full mt-1"
                                            :disabled="$isFormLocked" wire:model="formEresepRacikan.sedia"
                                            x-ref="sedia" x-init="$nextTick(() => $el.focus())"
                                            x-on:keydown.enter.prevent="$refs.dosis.focus()" />
                                    </div>

                                    {{-- Dosis --}}
                                    <div class="flex-[1]">
                                        <x-input-label :value="__('Dosis')" :required="true" />
                                        <x-text-input placeholder="Dosis" class="w-full mt-1"
                                            :disabled="$isFormLocked" wire:model="formEresepRacikan.dosis"
                                            x-ref="dosis" x-on:keydown.enter.prevent="$refs.qty.focus()" />
                                    </div>

                                    {{-- Qty --}}
                                    <div class="flex-[1]">
                                        <x-input-label :value="__('Jml')" />
                                        <x-text-input placeholder="Jml" class="w-full mt-1"
                                            :disabled="$isFormLocked" wire:model="formEresepRacikan.qty"
                                            x-ref="qty" x-on:keydown.enter.prevent="$refs.catatan.focus()" />
                                    </div>

                                    {{-- Catatan --}}
                                    <div class="flex-[2]">
                                        <x-input-label :value="__('Catatan')" />
                                        <x-text-input placeholder="Catatan" class="w-full mt-1"
                                            :disabled="$isFormLocked" wire:model="formEresepRacikan.catatan"
                                            x-ref="catatan"
                                            x-on:keydown.enter.prevent="$refs.signa.focus()" />
                                    </div>

                                    {{-- Signa --}}
                                    <div class="flex-[2]">
                                        <x-input-label :value="__('Signa')" />
                                        <x-text-input placeholder="Signa" class="w-full mt-1"
                                            :disabled="$isFormLocked" wire:model="formEresepRacikan.catatanKhusus"
                                            x-ref="signa"
                                            x-on:keydown.enter.prevent="$wire.insertProduct()" />
                                    </div>

                                    {{-- Hapus draft --}}
                                    <div class="ml-auto shrink-0">
                                        <x-input-label value="" />
                                        <x-secondary-button class="inline-flex mt-1"
                                            wire:click="resetFormEresepRacikan">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 18 20">
                                                <path
                                                    d="M17 4h-4V2a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v2H1a1 1 0 0 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6h1a1 1 0 1 0 0-2ZM7 2h4v2H7V2Zm1 14a1 1 0 1 1-2 0V8a1 1 0 0 1 2 0v8Z" />
                                            </svg>
                                        </x-secondary-button>
                                    </div>
                                </div>

                                {{-- Errors --}}
                                <div class="flex w-full gap-1 text-xs">
                                    <div class="flex-[1]"></div>
                                    <div class="flex-[3]">
                                        <x-input-error :messages="$errors->get('formEresepRacikan.productName')" />
                                    </div>
                                    <div class="flex-[1]">
                                        <x-input-error :messages="$errors->get('formEresepRacikan.sedia')" />
                                    </div>
                                    <div class="flex-[1]">
                                        <x-input-error :messages="$errors->get('formEresepRacikan.dosis')" />
                                    </div>
                                    <div class="flex-[1]">
                                        <x-input-error :messages="$errors->get('formEresepRacikan.qty')" />
                                    </div>
                                    <div class="flex-[2]">
                                        <x-input-error :messages="$errors->get('formEresepRacikan.catatan')" />
                                    </div>
                                    <div class="flex-[2]">
                                        <x-input-error :messages="$errors->get('formEresepRacikan.catatanKhusus')" />
                                    </div>
                                    <div class="ml-auto shrink-0"></div>
                                </div>
                            @endif

                        </div>
                    @endif
                @endrole

                {{-- Tabel Obat Racikan --}}
                <div class="flex flex-col my-2">
                    <div class="overflow-x-auto rounded-lg">
                        <div class="inline-block min-w-full align-middle">
                            <div class="overflow-hidden shadow sm:rounded-lg">
                                <table class="w-full text-sm text-left text-gray-500 table-auto dark:text-gray-400">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                        <tr>
                                            <th class="w-28 px-4 py-3">Racikan</th>
                                            <th class="px-4 py-3">Nama Obat</th>
                                            <th class="w-16 px-4 py-3">Sedia</th>
                                            <th class="w-24 px-4 py-3">Dosis</th>
                                            <th class="w-20 px-4 py-3">Jml</th>
                                            <th class="px-4 py-3">Catatan</th>
                                            <th class="px-4 py-3">Signa</th>
                                            <th class="w-8 px-4 py-3 text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white">
                                        @isset($dataDaftarRI['eresepHdr'][$resepIndex]['eresepRacikan'])
                                            @php $prevRacikan = null; @endphp
                                            @foreach ($dataDaftarRI['eresepHdr'][$resepIndex]['eresepRacikan'] as $key => $eresep)
                                                @php
                                                    $borderClass = $prevRacikan !== ($eresep['noRacikan'] ?? '')
                                                        ? 'border-t-2 border-red-400'
                                                        : 'border-t border-gray-200';
                                                @endphp
                                                <tr class="{{ $borderClass }} group" x-data>
                                                    <td class="w-28 px-4 py-3 whitespace-nowrap">
                                                        {{ ($eresep['jenisKeterangan'] ?? 'Racikan') . ' (' . ($eresep['noRacikan'] ?? '') . ')' }}
                                                    </td>
                                                    <td class="px-4 py-3">{{ $eresep['productName'] }}</td>
                                                    <td class="w-16 px-4 py-3">{{ $eresep['sedia'] }}</td>
                                                    <td class="w-24 px-4 py-3">
                                                        <x-text-input placeholder="Dosis" :disabled="!$isResepEditable"
                                                            wire:model="dataDaftarRI.eresepHdr.{{ $resepIndex }}.eresepRacikan.{{ $key }}.dosis"
                                                            x-ref="dosis{{ $key }}"
                                                            x-on:keydown.enter.prevent="$refs.qty{{ $key }}.focus()" />
                                                    </td>
                                                    <td class="w-20 px-4 py-3">
                                                        <x-text-input placeholder="Jml" :disabled="!$isResepEditable"
                                                            wire:model="dataDaftarRI.eresepHdr.{{ $resepIndex }}.eresepRacikan.{{ $key }}.qty"
                                                            x-ref="qty{{ $key }}"
                                                            x-on:keydown.enter.prevent="$refs.catatan{{ $key }}.focus()" />
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <x-text-input placeholder="Catatan" :disabled="!$isResepEditable"
                                                            wire:model="dataDaftarRI.eresepHdr.{{ $resepIndex }}.eresepRacikan.{{ $key }}.catatan"
                                                            x-ref="catatan{{ $key }}"
                                                            x-on:keydown.enter.prevent="$refs.signa{{ $key }}.focus()" />
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <x-text-input placeholder="Signa" :disabled="!$isResepEditable"
                                                            wire:model="dataDaftarRI.eresepHdr.{{ $resepIndex }}.eresepRacikan.{{ $key }}.catatanKhusus"
                                                            x-ref="signa{{ $key }}"
                                                            x-on:keydown.enter.prevent="
                                                                $wire.updateProduct(
                                                                    '{{ $eresep['riObatDtl'] }}',
                                                                    $wire.dataDaftarRI.eresepHdr[{{ $resepIndex }}].eresepRacikan[{{ $key }}].qty,
                                                                    $wire.dataDaftarRI.eresepHdr[{{ $resepIndex }}].eresepRacikan[{{ $key }}].dosis,
                                                                    $wire.dataDaftarRI.eresepHdr[{{ $resepIndex }}].eresepRacikan[{{ $key }}].catatan,
                                                                    $wire.dataDaftarRI.eresepHdr[{{ $resepIndex }}].eresepRacikan[{{ $key }}].catatanKhusus
                                                                );
                                                                $nextTick(() => $refs.dosis{{ $key }}.focus())
                                                            " />
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
                                                @php $prevRacikan = $eresep['noRacikan'] ?? ''; @endphp
                                            @endforeach
                                        @endisset
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
