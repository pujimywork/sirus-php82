{{-- pages/transaksi/ugd/emr-ugd/anamnesa/tabs/riwayat-penyakit-dahulu-tab.blade.php --}}
<x-border-form :title="__('Riwayat & Alergi')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="mt-4 space-y-4">

        {{-- Riwayat Penyakit Dahulu --}}
        <div>
            <x-input-label value="Riwayat Penyakit Dahulu" :required="true" />
            <x-textarea wire:model.live="dataDaftarUGD.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu"
                placeholder="Riwayat Perjalanan Penyakit" :error="$errors->has('dataDaftarUGD.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu')" :disabled="$isFormLocked" :rows="3"
                class="w-full mt-1" />
            <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu')" class="mt-1" />
        </div>

        {{-- Alergi --}}
        <div>
            <x-input-label value="Alergi" :required="false" />
            <x-textarea wire:model.live="dataDaftarUGD.anamnesa.alergi.alergi"
                placeholder="Jenis Alergi — Makanan / Obat / Udara" :error="$errors->has('dataDaftarUGD.anamnesa.alergi.alergi')" :disabled="$isFormLocked"
                :rows="3" class="w-full mt-1" />
            <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.alergi.alergi')" class="mt-1" />
        </div>

    </div>
</x-border-form>
