<x-border-form :title="__('Pemeriksaan Fisik')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="">

        <x-textarea id="dataDaftarPoliRJ.pemeriksaan.fisik" wire:model.live="dataDaftarPoliRJ.pemeriksaan.fisik"
            placeholder="Pemeriksaan Fisik" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.fisik')" :disabled="$isFormLocked" rows="3" class="w-full" />

        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.fisik')" class="mt-1" />

    </div>
</x-border-form>
