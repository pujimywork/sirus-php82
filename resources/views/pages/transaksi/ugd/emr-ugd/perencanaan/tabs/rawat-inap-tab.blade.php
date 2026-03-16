{{-- pages/transaksi/ugd/emr-ugd/perencanaan/tabs/rawat-inap-tab.blade.php --}}
<x-border-form :title="__('Rawat Inap')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="mt-4 space-y-4">

        {{-- No. Referensi --}}
        <div>
            <x-input-label value="No. Referensi" />
            <x-text-input placeholder="No. Referensi Rawat Inap" class="mt-1" :error="$errors->has('dataDaftarUGD.perencanaan.rawatInap.noRef')" :disabled="$isFormLocked"
                wire:model.live="dataDaftarUGD.perencanaan.rawatInap.noRef" />
            <x-input-error :messages="$errors->get('dataDaftarUGD.perencanaan.rawatInap.noRef')" class="mt-1" />
        </div>

        {{-- Tanggal --}}
        <div>
            <x-input-label value="Tanggal" />
            <x-text-input placeholder="Tanggal [dd/mm/yyyy]" class="mt-1" :error="$errors->has('dataDaftarUGD.perencanaan.rawatInap.tanggal')" :disabled="$isFormLocked"
                wire:model.live="dataDaftarUGD.perencanaan.rawatInap.tanggal" />
            <x-input-error :messages="$errors->get('dataDaftarUGD.perencanaan.rawatInap.tanggal')" class="mt-1" />
        </div>

        {{-- Keterangan --}}
        <div>
            <x-input-label value="Keterangan" />
            <x-textarea placeholder="Keterangan Rawat Inap" class="mt-1" :rows="3" :error="$errors->has('dataDaftarUGD.perencanaan.rawatInap.keterangan')"
                :disabled="$isFormLocked" wire:model.live="dataDaftarUGD.perencanaan.rawatInap.keterangan" />
            <x-input-error :messages="$errors->get('dataDaftarUGD.perencanaan.rawatInap.keterangan')" class="mt-1" />
        </div>

    </div>
</x-border-form>
