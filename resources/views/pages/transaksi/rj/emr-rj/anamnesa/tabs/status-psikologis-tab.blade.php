<div class="w-full mb-1">
    <div class="pt-2">
        <x-input-label for="dataDaftarPoliRJ.anamnesa.statusPsikologis.statusPsikologis" :value="__('Status Psikologis')"
            :required="__(false)" class="pt-2 sm:text-xl" />

        <div class="grid grid-cols-4 gap-2 pt-2">
            {{-- Mengganti checkbox dengan toggle switch --}}
            <div class="flex items-center space-x-2">
                <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.statusPsikologis.tidakAdaKelainan" trueValue="1"
                    falseValue="0">
                    {{ __('Tidak Ada Kelainan') }}
                </x-toggle>
            </div>

            <div class="flex items-center space-x-2">
                <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.statusPsikologis.marah" trueValue="1"
                    falseValue="0">
                    {{ __('Marah') }}
                </x-toggle>
            </div>

            <div class="flex items-center space-x-2">
                <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.statusPsikologis.ccemas" trueValue="1"
                    falseValue="0">
                    {{ __('Cemas') }}
                </x-toggle>
            </div>

            <div class="flex items-center space-x-2">
                <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.statusPsikologis.takut" trueValue="1"
                    falseValue="0">
                    {{ __('Takut') }}
                </x-toggle>
            </div>

            <div class="flex items-center space-x-2">
                <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.statusPsikologis.sedih" trueValue="1"
                    falseValue="0">
                    {{ __('Sedih') }}
                </x-toggle>
            </div>

            <div class="flex items-center space-x-2">
                <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.statusPsikologis.cenderungBunuhDiri" trueValue="1"
                    falseValue="0">
                    {{ __('Resiko Bunuh Diri') }}
                </x-toggle>
            </div>
        </div>

        <div class="mb-2">
            <x-text-input id="dataDaftarPoliRJ.anamnesa.statusPsikologis.statusPsikologis" placeholder="Lainnya"
                class="mt-1 ml-2" :errorshas="__($errors->has('dataDaftarPoliRJ.anamnesa.statusPsikologis.sebutstatusPsikologis'))" :disabled="$isFormLocked"
                wire:model.live="dataDaftarPoliRJ.anamnesa.statusPsikologis.sebutstatusPsikologis" />
        </div>

        {{-- Diubah menggunakan pattern x-input-error langsung --}}
        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.statusPsikologis.sebutstatusPsikologis')" class="mt-1" />
    </div>

    <div class="pt-2">
        <x-input-label for="dataDaftarPoliRJ.anamnesa.statusMental.statusMental" :value="__('Status Mental')" :required="__(false)"
            class="pt-2 sm:text-xl" />

        <div class="pt-2">
            <div class="grid grid-cols-3 gap-2 mt-2 ml-2">
                @foreach ($dataDaftarPoliRJ['anamnesa']['statusMental']['statusMentalOption'] as $statusMentalOption)
                    <x-radio-button :label="$statusMentalOption['statusMental']" :value="$statusMentalOption['statusMental']"
                        name="dataDaftarPoliRJ.anamnesa.statusMental.statusMental"
                        wire:model.live="dataDaftarPoliRJ.anamnesa.statusMental.statusMental" :disabled="$isFormLocked" />
                @endforeach
            </div>
        </div>

        <div class="mb-2">
            <x-text-input id="dataDaftarPoliRJ.anamnesa.statusMental.statusMental" placeholder="Lainnya"
                class="mt-1 ml-2" :errorshas="__($errors->has('dataDaftarPoliRJ.anamnesa.statusMental.statusMental'))" :disabled="$isFormLocked"
                wire:model.live="dataDaftarPoliRJ.anamnesa.statusMental.sebutstatusPsikologis" />
        </div>

        {{-- Diubah menggunakan pattern x-input-error langsung --}}
        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.statusMental.sebutstatusPsikologis')" class="mt-1" />
    </div>
</div>
