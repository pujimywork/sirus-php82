<div class="w-full mb-1">
    <div class="pt-2">
        <x-input-label for="dataDaftarPoliRJ.anamnesa.batuk.screeningBatuk" :value="__('Screening Batuk')" :required="__(false)"
            class="pt-2 sm:text-xl" />

        <div class="pt-2">
            {{-- Riwayat Demam --}}
            <div class="grid grid-cols-2 gap-2 mt-2">
                <div class="flex items-center space-x-2">
                    <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.batuk.riwayatDemam" trueValue="1" falseValue="0">
                        {{ __('Riwayat Demam?') }}
                    </x-toggle>
                </div>

                <x-text-input id="dataDaftarPoliRJ.anamnesa.batuk.keteranganRiwayatDemam"
                    placeholder="Keterangan Riwayat Demam" class="mt-1 ml-2" :errorshas="__($errors->has('dataDaftarPoliRJ.anamnesa.batuk.keteranganRiwayatDemam'))" :disabled="$isFormLocked"
                    wire:model.live="dataDaftarPoliRJ.anamnesa.batuk.keteranganRiwayatDemam" />
            </div>
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.batuk.keteranganRiwayatDemam')" class="mt-1" />

            {{-- Riwayat Berkeringat Malam Hari --}}
            <div class="grid grid-cols-2 gap-2 mt-2">
                <div class="flex items-center space-x-2">
                    <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.batuk.berkeringatMlmHari" trueValue="1"
                        falseValue="0">
                        {{ __('Riwayat Berkeringat Malam Hari Tanpa Aktifitas?') }}
                    </x-toggle>
                </div>

                <x-text-input id="dataDaftarPoliRJ.anamnesa.batuk.keteranganBerkeringatMlmHari"
                    placeholder="Keterangan Berkeringat Malam Hari Tanpa Aktifitas" class="mt-1 ml-2" :errorshas="__($errors->has('dataDaftarPoliRJ.anamnesa.batuk.keteranganBerkeringatMlmHari'))"
                    :disabled="$isFormLocked" wire:model.live="dataDaftarPoliRJ.anamnesa.batuk.keteranganBerkeringatMlmHari" />
            </div>
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.batuk.keteranganBerkeringatMlmHari')" class="mt-1" />

            {{-- Riwayat Bepergian Daerah Wabah --}}
            <div class="grid grid-cols-2 gap-2 mt-2">
                <div class="flex items-center space-x-2">
                    <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.batuk.bepergianDaerahWabah" trueValue="1"
                        falseValue="0">
                        {{ __('Riwayat Bepergian Daerah Wabah?') }}
                    </x-toggle>
                </div>

                <x-text-input id="dataDaftarPoliRJ.anamnesa.batuk.keteranganBepergianDaerahWabah"
                    placeholder="Keterangan Bepergian Daerah Wabah" class="mt-1 ml-2" :errorshas="__($errors->has('dataDaftarPoliRJ.anamnesa.batuk.keteranganBepergianDaerahWabah'))"
                    :disabled="$isFormLocked"
                    wire:model.live="dataDaftarPoliRJ.anamnesa.batuk.keteranganBepergianDaerahWabah" />
            </div>
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.batuk.keteranganBepergianDaerahWabah')" class="mt-1" />

            {{-- Riwayat Pemakaian Obat Jangka Panjang --}}
            <div class="grid grid-cols-2 gap-2 mt-2">
                <div class="flex items-center space-x-2">
                    <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.batuk.riwayatPakaiObatJangkaPanjangan"
                        trueValue="1" falseValue="0">
                        {{ __('Riwayat Pemakaian Obat dalam Jangka Panjang?') }}
                    </x-toggle>
                </div>

                <x-text-input id="dataDaftarPoliRJ.anamnesa.batuk.keteranganRiwayatPakaiObatJangkaPanjangan"
                    placeholder="Keterangan Riwayat Pemakaian Obat" class="mt-1 ml-2" :errorshas="__(
                        $errors->has('dataDaftarPoliRJ.anamnesa.batuk.keteranganRiwayatPakaiObatJangkaPanjangan'),
                    )"
                    :disabled="$isFormLocked"
                    wire:model.live="dataDaftarPoliRJ.anamnesa.batuk.keteranganRiwayatPakaiObatJangkaPanjangan" />
            </div>
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.batuk.keteranganRiwayatPakaiObatJangkaPanjangan')" class="mt-1" />

            {{-- Riwayat Berat Badan Turun --}}
            <div class="grid grid-cols-2 gap-2 mt-2">
                <div class="flex items-center space-x-2">
                    <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.batuk.BBTurunTanpaSebab" trueValue="1"
                        falseValue="0">
                        {{ __('Riwayat Berat Badan Turun Tanpa Sebab?') }}
                    </x-toggle>
                </div>

                <x-text-input id="dataDaftarPoliRJ.anamnesa.batuk.keteranganBBTurunTanpaSebab"
                    placeholder="Keterangan Berat Badan Turun Tanpa Sebab" class="mt-1 ml-2" :errorshas="__($errors->has('dataDaftarPoliRJ.anamnesa.batuk.keteranganBBTurunTanpaSebab'))"
                    :disabled="$isFormLocked" wire:model.live="dataDaftarPoliRJ.anamnesa.batuk.keteranganBBTurunTanpaSebab" />
            </div>
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.batuk.keteranganBBTurunTanpaSebab')" class="mt-1" />

            {{-- Pembesaran Kelenjar Getah Bening --}}
            <div class="grid grid-cols-2 gap-2 mt-2">
                <div class="flex items-center space-x-2">
                    <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.batuk.pembesaranGetahBening" trueValue="1"
                        falseValue="0">
                        {{ __('Ada Pembesaran Kelenjar Getah Bening?') }}
                    </x-toggle>
                </div>

                <x-text-input id="dataDaftarPoliRJ.anamnesa.batuk.keteranganpembesaranGetahBening"
                    placeholder="Keterangan Pembesaran Kelenjar Getah Bening" class="mt-1 ml-2" :errorshas="__($errors->has('dataDaftarPoliRJ.anamnesa.batuk.keteranganpembesaranGetahBening'))"
                    :disabled="$isFormLocked"
                    wire:model.live="dataDaftarPoliRJ.anamnesa.batuk.keteranganpembesaranGetahBening" />
            </div>
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.batuk.keteranganpembesaranGetahBening')" class="mt-1" />
        </div>
    </div>
</div>
