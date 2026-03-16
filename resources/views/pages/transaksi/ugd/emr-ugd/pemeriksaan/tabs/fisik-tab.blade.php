{{-- pages/transaksi/ugd/emr-ugd/pemeriksaan/tabs/fisik-tab.blade.php --}}
<x-border-form :title="__('Pemeriksaan Fisik')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="mt-4">
        <x-textarea wire:model.live="dataDaftarUGD.pemeriksaan.fisik" placeholder="Pemeriksaan Fisik" :error="$errors->has('dataDaftarUGD.pemeriksaan.fisik')"
            :disabled="$isFormLocked" rows="3" class="w-full" />
        <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.fisik')" class="mt-1" />
    </div>
</x-border-form>
