<div class="mt-4 space-y-3">

    {{-- Textarea Terapi --}}
    <div>
        <x-textarea id="terapi" placeholder="Terapi" :error="$errors->has('dataDaftarPoliRJ.perencanaan.terapi.terapi')" :disabled="$isFormLocked" :rows="7"
            wire:model.live="dataDaftarPoliRJ.perencanaan.terapi.terapi" />
        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.perencanaan.terapi.terapi')" class="mt-1" />
    </div>

</div>
