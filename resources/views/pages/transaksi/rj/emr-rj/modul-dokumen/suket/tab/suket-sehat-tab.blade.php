{{-- SUKET SEHAT TAB --}}
<div class="pt-0">

    {{-- Keterangan Sehat --}}
    <div class="mb-2">
        <x-input-label for="dataDaftarPoliRJ.suket.suketSehat.suketSehat" :value="__('Keterangan')" :required="__(false)" />

        <x-textarea id="dataDaftarPoliRJ.suket.suketSehat.suketSehat"
            placeholder="Tuliskan keterangan surat sehat pasien..." class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.suket.suketSehat.suketSehat')" :disabled="$isFormLocked"
            wire:model.live="dataDaftarPoliRJ.suket.suketSehat.suketSehat" rows="6" />

        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.suket.suketSehat.suketSehat')" class="mt-1" />
    </div>

</div>
