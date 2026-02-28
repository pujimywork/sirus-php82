<div class="w-full mb-1">
    <div class="pt-0">
        <x-input-label for="dataDaftarPoliRJ.pemeriksaan.fisik" :value="__('Fisik')" :required="__(false)"
            class="pt-2 sm:text-xl" />

        <div class="mb-2">
            <x-input-label for="dataDaftarPoliRJ.pemeriksaan.fisik" :value="__('Pemeriksaan Fisik')" :required="__(false)" />

            <x-textarea id="dataDaftarPoliRJ.pemeriksaan.fisik" placeholder="Pemeriksaan Fisik" class="mt-1 ml-2"
                :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.fisik')" :disabled="$isFormLocked" wire:model.live="dataDaftarPoliRJ.pemeriksaan.fisik"
                rows="3" />
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.fisik')" class="mt-1" />
        </div>
    </div>
</div>
