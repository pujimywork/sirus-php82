<div>
    <div class="w-full mb-1">
        <div class="mb-2">
            <x-input-label for="terapi" :value="__('Terapi')" :required="__(false)" />
            <x-textarea id="terapi" placeholder="Terapi" class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.perencanaan.terapi.terapi')" :disabled="$isFormLocked" :rows=7
                wire:model.live="dataDaftarPoliRJ.perencanaan.terapi.terapi" />
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.perencanaan.terapi.terapi')" class="mt-1" />
        </div>

        @role(['Dokter', 'Admin'])
            @if (!$isFormLocked)
                <div class="grid grid-cols-1 gap-2">
                    <x-primary-button :disabled=false wire:click.prevent="openModalEresepRJ" type="button"
                        wire:loading.remove>
                        E-resep
                    </x-primary-button>
                </div>
            @endif
        @endrole
    </div>
</div>
