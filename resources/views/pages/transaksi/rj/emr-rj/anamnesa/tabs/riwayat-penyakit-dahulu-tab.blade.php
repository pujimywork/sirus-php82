<div class="w-full mb-1">
    {{-- Field Riwayat Penyakit Dahulu --}}
    <div>
        <x-input-label for="dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu" :value="__('Riwayat Penyakit Dahulu')"
            :required="__(true)" class="pt-2 " />

        <div class="mb-2">
            <x-textarea id="dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu"
                placeholder="Riwayat Perjalanan Penyakit" class="mt-1 ml-2" :errorshas="__($errors->has('dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu'))" :disabled="$isFormLocked"
                :rows="3"
                wire:model.live="dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu" />
        </div>

        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu')" class="mt-1" />
    </div>

    {{-- Field Alergi --}}
    <div>
        <x-input-label for="dataDaftarPoliRJ.anamnesa.alergi.alergi" :value="__('Alergi')" :required="__(false)"
            class="pt-2 " />

        <div class="mb-2">
            <x-textarea id="dataDaftarPoliRJ.anamnesa.alergi.alergi"
                placeholder="Jenis Alergi / Alergi [Makanan / Obat / Udara]" class="mt-1 ml-2" :errorshas="__($errors->has('dataDaftarPoliRJ.anamnesa.alergi.alergi'))"
                :disabled="$isFormLocked" :rows="3" wire:model.live="dataDaftarPoliRJ.anamnesa.alergi.alergi" />
        </div>

        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.alergi.alergi')" class="mt-1" />
    </div>
</div>
