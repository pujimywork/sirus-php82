<x-border-form :title="__('Pemeriksaan Penunjang')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="">
        <x-textarea id="dataDaftarPoliRJ.pemeriksaan.penunjang" wire:model.live="dataDaftarPoliRJ.pemeriksaan.penunjang"
            placeholder="Laborat / Foto / EKG / Lain-lain" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.penunjang')" :disabled="$isFormLocked" rows="3" class="w-full" />
        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.penunjang')" class="mt-1" />
    </div>
</x-border-form>
