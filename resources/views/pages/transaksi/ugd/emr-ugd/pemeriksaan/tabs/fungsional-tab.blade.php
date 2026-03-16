{{-- pages/transaksi/ugd/emr-ugd/pemeriksaan/tabs/fungsional-tab.blade.php --}}
<x-border-form :title="__('Fungsional')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">

        {{-- Alat Bantu --}}
        <div>
            <x-input-label value="Alat Bantu" />
            <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.fungsional.alatBantu" placeholder="Alat Bantu"
                :error="$errors->has('dataDaftarUGD.pemeriksaan.fungsional.alatBantu')" :disabled="$isFormLocked" class="w-full mt-1" />
            <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.fungsional.alatBantu')" class="mt-1" />
        </div>

        {{-- Prothesa --}}
        <div>
            <x-input-label value="Prothesa" />
            <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.fungsional.prothesa" placeholder="Prothesa"
                :error="$errors->has('dataDaftarUGD.pemeriksaan.fungsional.prothesa')" :disabled="$isFormLocked" class="w-full mt-1" />
            <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.fungsional.prothesa')" class="mt-1" />
        </div>

        {{-- Cacat Tubuh --}}
        <div>
            <x-input-label value="Cacat Tubuh" />
            <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.fungsional.cacatTubuh" placeholder="Cacat Tubuh"
                :error="$errors->has('dataDaftarUGD.pemeriksaan.fungsional.cacatTubuh')" :disabled="$isFormLocked" class="w-full mt-1" />
            <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.fungsional.cacatTubuh')" class="mt-1" />
        </div>

    </div>
</x-border-form>
