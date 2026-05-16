{{-- pages/transaksi/ugd/emr-ugd/perencanaan/tabs/terapi-tab.blade.php --}}
<div class="mt-4 space-y-3">

    {{-- Textarea Terapi --}}
    <div>
        <x-textarea placeholder="Terapi" :error="$errors->has('dataDaftarUGD.perencanaan.terapi.terapi')" :disabled="$isFormLocked" :rows="7"
            wire:model.live="dataDaftarUGD.perencanaan.terapi.terapi" />
        <x-input-error :messages="$errors->get('dataDaftarUGD.perencanaan.terapi.terapi')" class="mt-1" />
    </div>

</div>
