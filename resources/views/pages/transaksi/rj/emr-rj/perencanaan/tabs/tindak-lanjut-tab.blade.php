<div>
    <div class="w-full mb-1">
        <x-input-label for="tindakLanjut" :value="__('Tindak Lanjut')" :required="__(false)" class="pt-2 sm:text-xl" />

        <div class="pt-2">
            <div class="mt-2 mb-2 ml-2">
                <x-select-input id="tindakLanjut" wire:model.live="dataDaftarPoliRJ.perencanaan.tindakLanjut.tindakLanjut"
                    :disabled="$isFormLocked" :error="$errors->has('dataDaftarPoliRJ.perencanaan.tindakLanjut.tindakLanjut')">
                    <option value="">Pilih Tindak Lanjut</option>
                    @foreach ($dataDaftarPoliRJ['perencanaan']['tindakLanjut']['tindakLanjutOptions'] ?? [] as $option)
                        <option value="{{ $option['tindakLanjut'] }}">
                            {{ __($option['tindakLanjut']) }}
                        </option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.perencanaan.tindakLanjut.tindakLanjut')" class="mt-1" />
            </div>

            <div class="mt-4">
                <x-text-input id="keteranganTindakLanjut" placeholder="Keterangan Tindak Lanjut" class="mt-1 ml-2"
                    :error="$errors->has('dataDaftarPoliRJ.perencanaan.tindakLanjut.keteranganTindakLanjut')" :disabled="$isFormLocked"
                    wire:model.live="dataDaftarPoliRJ.perencanaan.tindakLanjut.keteranganTindakLanjut" />
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.perencanaan.tindakLanjut.keteranganTindakLanjut')" class="mt-1" />
            </div>

            @if (!$isFormLocked)
                <div class="mt-4">
                    <x-primary-button :disabled=false wire:click.prevent="setStatusPRB" type="button"
                        wire:loading.remove>
                        Set Status PRB
                    </x-primary-button>
                </div>
            @endif
        </div>
    </div>
</div>
