{{-- pages/transaksi/ugd/emr-ugd/perencanaan/tabs/terapi-tab.blade.php --}}
<div class="mt-4 space-y-3">

    {{-- Textarea Terapi --}}
    <div>
        <x-textarea placeholder="Terapi" :error="$errors->has('dataDaftarUGD.perencanaan.terapi.terapi')" :disabled="$isFormLocked" :rows="7"
            wire:model.live="dataDaftarUGD.perencanaan.terapi.terapi" />
        <x-input-error :messages="$errors->get('dataDaftarUGD.perencanaan.terapi.terapi')" class="mt-1" />
    </div>

    {{-- Tombol E-Resep --}}
    @role(['Dokter', 'Admin'])
        @if (!$isFormLocked)
            <x-outline-button type="button" class="justify-center w-full" wire:click.prevent="openModalEresepUGD"
                wire:loading.attr="disabled" wire:target="openModalEresepUGD">
                <span wire:loading.remove wire:target="openModalEresepUGD" class="inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2 2 0 002-2V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                    </svg>
                    E-Resep
                </span>
                <span wire:loading wire:target="openModalEresepUGD" class="inline-flex items-center gap-2">
                    <x-loading /> Memuat...
                </span>
            </x-outline-button>
        @endif
    @endrole

</div>
