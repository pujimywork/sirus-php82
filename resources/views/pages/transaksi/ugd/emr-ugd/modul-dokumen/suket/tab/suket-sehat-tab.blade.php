{{-- pages/transaksi/ugd/emr-ugd/modul-dokumen/suket/tab/suket-sehat-tab.blade.php --}}
<div class="pt-0">

    {{-- Keterangan Sehat --}}
    <div class="mb-2">
        <x-input-label value="Keterangan" :required="false" />
        <x-textarea placeholder="Tuliskan keterangan surat sehat pasien..." class="mt-1 ml-2" :error="$errors->has('dataDaftarUGD.suket.suketSehat.suketSehat')"
            :disabled="$isFormLocked" wire:model.live="dataDaftarUGD.suket.suketSehat.suketSehat" rows="6" />
        <x-input-error :messages="$errors->get('dataDaftarUGD.suket.suketSehat.suketSehat')" class="mt-1" />
    </div>

    {{-- TOMBOL CETAK --}}
    <div class="flex justify-end mt-3">
        <x-secondary-button wire:click="cetakSuketSehat" wire:loading.attr="disabled" wire:target="cetakSuketSehat">
            <span wire:loading.remove wire:target="cetakSuketSehat" class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Surat Sehat
            </span>
            <span wire:loading wire:target="cetakSuketSehat" class="flex items-center gap-1">
                <x-loading /> Mencetak...
            </span>
        </x-secondary-button>
    </div>

</div>
