{{-- pages/transaksi/ugd/emr-ugd/pemeriksaan/tabs/penunjang-tab.blade.php --}}
<x-border-form :title="__('Pemeriksaan Penunjang')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="mt-4">
        <p class="mb-2 text-xs text-gray-400">Lab / Foto / EKG / Lain-lain</p>
        <x-textarea wire:model.live="dataDaftarUGD.pemeriksaan.penunjang" placeholder="Pemeriksaan Penunjang"
            :error="$errors->has('dataDaftarUGD.pemeriksaan.penunjang')" :disabled="$isFormLocked" rows="3" class="w-full" />
        <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.penunjang')" class="mt-1" />
    </div>
</x-border-form>
