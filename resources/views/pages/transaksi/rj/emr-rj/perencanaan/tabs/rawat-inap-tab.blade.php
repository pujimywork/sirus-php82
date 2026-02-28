<div>
    <div class="w-full mb-1">
        <div class="mb-2">
            <x-input-label for="noRef" :value="__('No. Referensi')" :required="__(false)" />
            <x-text-input id="noRef" placeholder="No. Referensi Rawat Inap" class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.perencanaan.rawatInap.noRef')"
                :disabled="$isFormLocked" wire:model.live="dataDaftarPoliRJ.perencanaan.rawatInap.noRef" />
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.perencanaan.rawatInap.noRef')" class="mt-1" />
        </div>

        <div class="mb-2">
            <x-input-label for="tanggal" :value="__('Tanggal')" :required="__(false)" />
            <x-text-input id="tanggal" placeholder="Tanggal [dd/mm/yyyy]" class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.perencanaan.rawatInap.tanggal')"
                :disabled="$isFormLocked" wire:model.live="dataDaftarPoliRJ.perencanaan.rawatInap.tanggal" />
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.perencanaan.rawatInap.tanggal')" class="mt-1" />
        </div>

        <div class="mb-2">
            <x-input-label for="keterangan" :value="__('Keterangan')" :required="__(false)" />
            <x-textarea id="keterangan" placeholder="Keterangan Rawat Inap" class="mt-1 ml-2" :rows=3 :error="$errors->has('dataDaftarPoliRJ.perencanaan.rawatInap.keterangan')"
                :disabled="$isFormLocked" wire:model.live="dataDaftarPoliRJ.perencanaan.rawatInap.keterangan" />
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.perencanaan.rawatInap.keterangan')" class="mt-1" />
        </div>
    </div>
</div>
