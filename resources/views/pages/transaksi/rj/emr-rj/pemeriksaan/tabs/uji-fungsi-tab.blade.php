<div class="w-full mb-1">
    <div class="pt-0">
        <x-input-label for="dataDaftarPoliRJ.pemeriksaan.FisikujiFungsi.FisikujiFungsi" :value="__('Pemeriksaan Fisik dan Uji Fungsi')"
            :required="__(false)" class="pt-2 sm:text-xl" />

        <div class="mb-2">
            <x-textarea id="dataDaftarPoliRJ.pemeriksaan.FisikujiFungsi.FisikujiFungsi"
                placeholder="Pemeriksaan Fisik dan Uji Fungsi" class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.FisikujiFungsi.FisikujiFungsi')" :disabled="$isFormLocked"
                wire:model.live="dataDaftarPoliRJ.pemeriksaan.FisikujiFungsi.FisikujiFungsi" rows="3" />
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.FisikujiFungsi.FisikujiFungsi')" class="mt-1" />
        </div>
    </div>
</div>
