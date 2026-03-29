{{-- pages/transaksi/ugd/emr-ugd/anamnesa/tabs/riwayat-penyakit-sekarang-tab.blade.php --}}
<x-border-form :title="__('Riwayat Penyakit Sekarang')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="mt-4">
        <x-textarea wire:model.live="dataDaftarUGD.anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum"
            placeholder="Deskripsi Anamnesis" :error="$errors->has('dataDaftarUGD.anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum')" :disabled="$isFormLocked" :rows="3" class="w-full" />
        <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum')" class="mt-1" />
    </div>
</x-border-form>
