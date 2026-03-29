<x-border-form :title="__('Riwayat Penyakit Sekarang')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="mt-4">

        <x-textarea id="dataDaftarPoliRJ.anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum"
            wire:model.live="dataDaftarPoliRJ.anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum"
            placeholder="Deskripsi Anamnesis" :error="$errors->has(
                'dataDaftarPoliRJ.anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum',
            )" :disabled="$isFormLocked" :rows="3" class="w-full" />

        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum')" class="mt-1" />

    </div>
</x-border-form>
