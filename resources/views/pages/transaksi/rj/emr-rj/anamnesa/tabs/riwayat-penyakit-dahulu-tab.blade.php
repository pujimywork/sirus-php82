<x-border-form :title="__('Riwayat & Alergi')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="mt-4 space-y-4">

        {{-- Riwayat Penyakit Dahulu --}}
        <div>
            <x-input-label for="dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu"
                value="Riwayat Penyakit Dahulu" :required="true" />

            <x-textarea id="dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu"
                wire:model.live="dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu"
                placeholder="Riwayat Perjalanan Penyakit" :error="$errors->has('dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu')" :disabled="$isFormLocked" :rows="3"
                class="w-full mt-1" />

            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu')" class="mt-1" />
        </div>

        {{-- Alergi --}}
        <div>
            <x-input-label for="dataDaftarPoliRJ.anamnesa.alergi.alergi" value="Alergi" :required="false" />

            <x-textarea id="dataDaftarPoliRJ.anamnesa.alergi.alergi"
                wire:model.live="dataDaftarPoliRJ.anamnesa.alergi.alergi"
                placeholder="Jenis Alergi — Makanan / Obat / Udara" :error="$errors->has('dataDaftarPoliRJ.anamnesa.alergi.alergi')" :disabled="$isFormLocked"
                :rows="3" class="w-full mt-1" />

            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.alergi.alergi')" class="mt-1" />
        </div>

    </div>
</x-border-form>
