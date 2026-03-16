{{-- pages/transaksi/ugd/emr-ugd/perencanaan/tabs/petugas-medis-tab.blade.php --}}
<div class="space-y-4">

    {{-- Terapi --}}
    <x-border-form :title="__('Terapi')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4">
            @include('pages.transaksi.ugd.emr-ugd.perencanaan.tabs.terapi-tab')
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Waktu Pemeriksaan:
                <span class="font-medium text-gray-700 dark:text-gray-200">
                    {{ $dataDaftarUGD['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] ?? '-' }}
                </span>
            </p>
        </div>
    </x-border-form>

    {{-- Dokter Pemeriksa --}}
    <x-border-form :title="__('Dokter Pemeriksa')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 space-y-3">
            <x-text-input placeholder="Dokter Pemeriksa" :error="$errors->has('dataDaftarUGD.perencanaan.pengkajianMedis.drPemeriksa')" :disabled="true"
                wire:model="dataDaftarUGD.perencanaan.pengkajianMedis.drPemeriksa" />
            <x-input-error :messages="$errors->get('dataDaftarUGD.perencanaan.pengkajianMedis.drPemeriksa')" class="mt-1" />

            @if (!$isFormLocked)
                <x-outline-button type="button" class="justify-center w-full" wire:click.prevent="setDrPemeriksa"
                    wire:loading.attr="disabled" wire:target="setDrPemeriksa">
                    <span wire:loading.remove wire:target="setDrPemeriksa" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        TTD Dokter
                    </span>
                    <span wire:loading wire:target="setDrPemeriksa" class="inline-flex items-center gap-2">
                        <x-loading /> Menyimpan...
                    </span>
                </x-outline-button>
            @endif

            <p class="text-xs text-gray-500 dark:text-gray-400">
                Selesai Pemeriksaan:
                <span class="font-medium text-gray-700 dark:text-gray-200">
                    {{ $dataDaftarUGD['perencanaan']['pengkajianMedis']['selesaiPemeriksaan'] ?? '-' }}
                </span>
            </p>
        </div>
    </x-border-form>

</div>
