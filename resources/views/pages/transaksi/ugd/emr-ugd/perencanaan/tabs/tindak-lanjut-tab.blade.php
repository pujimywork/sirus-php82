{{-- pages/transaksi/ugd/emr-ugd/perencanaan/tabs/tindak-lanjut-tab.blade.php --}}
<x-border-form :title="__('Tindak Lanjut')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="mt-4 space-y-4">

        {{-- Select Tindak Lanjut --}}
        <div>
            <x-select-input wire:model.live="dataDaftarUGD.perencanaan.tindakLanjut.tindakLanjut" :disabled="$isFormLocked"
                :error="$errors->has('dataDaftarUGD.perencanaan.tindakLanjut.tindakLanjut')">
                <option value="">Pilih Tindak Lanjut</option>
                @foreach ($dataDaftarUGD['perencanaan']['tindakLanjut']['tindakLanjutOptions'] ?? [] as $option)
                    <option value="{{ $option['tindakLanjut'] }}">
                        {{ __($option['tindakLanjut']) }}
                    </option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('dataDaftarUGD.perencanaan.tindakLanjut.tindakLanjut')" class="mt-1" />
        </div>

        {{-- Keterangan --}}
        <div>
            <x-text-input placeholder="Keterangan Tindak Lanjut" :error="$errors->has('dataDaftarUGD.perencanaan.tindakLanjut.keteranganTindakLanjut')" :disabled="$isFormLocked"
                wire:model.live="dataDaftarUGD.perencanaan.tindakLanjut.keteranganTindakLanjut" />
            <x-input-error :messages="$errors->get('dataDaftarUGD.perencanaan.tindakLanjut.keteranganTindakLanjut')" class="mt-1" />
        </div>

        {{-- Set Status PRB --}}
        @if (!$isFormLocked)
            <div>
                <x-primary-button wire:click.prevent="setStatusPRB" type="button" wire:loading.remove>
                    Set Status PRB
                </x-primary-button>
            </div>
        @endif

        {{-- Rujukan Antar RS — tampil hanya jika Tindak Lanjut = Rujuk --}}
        @if (($dataDaftarUGD['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '') === 'Rujuk')
            <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                <livewire:pages::transaksi.ugd.emr-ugd.rujukan-antar-rs.rm-rujukan-ugd-actions :rjNo="$rjNo"
                    wire:key="rm-rujukan-ugd-{{ $rjNo }}" />
            </div>
        @endif

    </div>
</x-border-form>
