{{-- SUKET ISTIRAHAT TAB --}}
<div class="pt-0">

    {{-- Mulai Istirahat --}}
    <div class="mb-2">
        <x-input-label for="dataDaftarPoliRJ.suket.suketIstirahat.mulaiIstirahat" :value="__('Mulai Istirahat')" :required="__(false)" />

        <x-select-input id="dataDaftarPoliRJ.suket.suketIstirahat.mulaiIstirahat" class="mt-1 ml-2" :disabled="$isFormLocked"
            :error="$errors->has('dataDaftarPoliRJ.suket.suketIstirahat.mulaiIstirahat')" wire:model.live="dataDaftarPoliRJ.suket.suketIstirahat.mulaiIstirahat">
            @foreach ($dataDaftarPoliRJ['suket']['suketIstirahat']['mulaiIstirahatOptions'] ?? [] as $option)
                <option value="{{ $option['mulaiIstirahat'] }}">
                    {{ $option['mulaiIstirahat'] }}
                </option>
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

</div>
