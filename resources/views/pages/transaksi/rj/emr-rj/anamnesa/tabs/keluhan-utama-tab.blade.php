<div class="w-full mb-1">
    <x-input-label for="dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama" :value="__('Keluhan Utama')" :required="__(true)"
        class="pt-2 " />

    <div class="mb-2">
        <x-textarea id="dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama" placeholder="Keluhan Utama" :errorshas="$errors->has('dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama')"
            :disabled="$isFormLocked" :rows="3" wire:model.live="dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama" />
    </div>

    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama')" class="mt-1" />
</div>
