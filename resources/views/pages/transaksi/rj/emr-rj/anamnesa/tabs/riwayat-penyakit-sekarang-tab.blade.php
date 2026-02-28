<div class="w-full mb-1">
    <div>
        <x-input-label for="dataDaftarPoliRJ.anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum"
            :value="__('Riwayat Penyakit Sekarang')" :required="__(true)" class="pt-2 " />

        <div class="mb-2">
            <x-textarea id="dataDaftarPoliRJ.anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum"
                placeholder="Deskripsi Anamnesis" class="mt-1 ml-2" :errorshas="__(
                    $errors->has('dataDaftarPoliRJ.anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum'),
                )" :disabled="$isFormLocked" :rows="3"
                wire:model.live="dataDaftarPoliRJ.anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum" />
        </div>

        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum')" class="mt-1" />
    </div>
</div>
