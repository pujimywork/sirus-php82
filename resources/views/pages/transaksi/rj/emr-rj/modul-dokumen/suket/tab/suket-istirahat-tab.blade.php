{{-- SUKET ISTIRAHAT TAB --}}
<div class="pt-0">

    {{-- Mulai Istirahat --}}
    <div class="mb-2">
        <x-input-label for="dataDaftarPoliRJ.suket.suketIstirahat.mulaiIstirahat" :value="__('Mulai Istirahat')" :required="__(false)" />

        <x-select-input id="dataDaftarPoliRJ.suket.suketIstirahat.mulaiIstirahat" class="mt-1 ml-2" :disabled="$isFormLocked"
            :error="$errors->has('dataDaftarPoliRJ.suket.suketIstirahat.mulaiIstirahat')" wire:model.live="dataDaftarPoliRJ.suket.suketIstirahat.mulaiIstirahat">
            @foreach ($dataDaftarPoliRJ['suket']['suketIstirahat']['mulaiIstirahatOptions'] ?? [] as $option)
                @php
                    // Kompat: struktur baru ['value','label'] + fallback struktur lama ['mulaiIstirahat']
                    $optValue = $option['value'] ?? $option['mulaiIstirahat'] ?? '';
                    $optLabel = $option['label'] ?? $option['mulaiIstirahat'] ?? '';
                @endphp
                <option value="{{ $optValue }}">{{ $optLabel }}</option>
            @endforeach
        </x-select-input>

        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.suket.suketIstirahat.mulaiIstirahat')" class="mt-1" />
    </div>

    {{-- Jumlah Hari Istirahat --}}
    <div class="mb-2">
        <x-input-label for="dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahatHari" :value="__('Jumlah Hari Istirahat')"
            :required="__(false)" />
        <x-text-input-mou id="dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahatHari" placeholder="0"
            class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahatHari')" :disabled="$isFormLocked" :mou_label="__('Hari')"
            wire:model.live="dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahatHari" />

        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahatHari')" class="mt-1" />
    </div>

    {{-- Keterangan Istirahat --}}
    <div class="mb-2">
        <x-input-label for="dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahat" :value="__('Keterangan')"
            :required="__(false)" />

        <x-textarea id="dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahat"
            placeholder="Tuliskan keterangan surat istirahat pasien..." class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahat')"
            :disabled="$isFormLocked" wire:model.live="dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahat" rows="6" />

        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahat')" class="mt-1" />
    </div>

    {{-- TOMBOL CETAK --}}
    <div class="flex justify-end mt-3">
        <x-secondary-button wire:click="cetakSuketSakit" wire:loading.attr="disabled" wire:target="cetakSuketSakit">
            <span wire:loading.remove wire:target="cetakSuketSakit" class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Surat Sakit
            </span>
            <span wire:loading wire:target="cetakSuketSakit" class="flex items-center gap-1">
                <x-loading />
                Mencetak...
            </span>
        </x-secondary-button>
    </div>

</div>
