<div class="space-y-4">

    {{-- Status Psikologis --}}
    <x-border-form :title="__('Status Psikologis')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 space-y-4">

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                <div class="flex items-center">
                    <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.statusPsikologis.tidakAdaKelainan" trueValue="1"
                        falseValue="0" :disabled="$isFormLocked">
                        {{ __('Tidak Ada Kelainan') }}
                    </x-toggle>
                </div>
                <div class="flex items-center">
                    <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.statusPsikologis.marah" trueValue="1"
                        falseValue="0" :disabled="$isFormLocked">
                        {{ __('Marah') }}
                    </x-toggle>
                </div>
                <div class="flex items-center">
                    <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.statusPsikologis.ccemas" trueValue="1"
                        falseValue="0" :disabled="$isFormLocked">
                        {{ __('Cemas') }}
                    </x-toggle>
                </div>
                <div class="flex items-center">
                    <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.statusPsikologis.takut" trueValue="1"
                        falseValue="0" :disabled="$isFormLocked">
                        {{ __('Takut') }}
                    </x-toggle>
                </div>
                <div class="flex items-center">
                    <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.statusPsikologis.sedih" trueValue="1"
                        falseValue="0" :disabled="$isFormLocked">
                        {{ __('Sedih') }}
                    </x-toggle>
                </div>
                <div class="flex items-center">
                    <x-toggle wire:model.live="dataDaftarPoliRJ.anamnesa.statusPsikologis.cenderungBunuhDiri"
                        trueValue="1" falseValue="0" :disabled="$isFormLocked">
                        {{ __('Risiko Bunuh Diri') }}
                    </x-toggle>
                </div>
            </div>

            <div>
                <x-input-label value="Lainnya" />
                <x-text-input wire:model.live="dataDaftarPoliRJ.anamnesa.statusPsikologis.sebutstatusPsikologis"
                    placeholder="Keterangan status psikologis lainnya" :error="$errors->has('dataDaftarPoliRJ.anamnesa.statusPsikologis.sebutstatusPsikologis')" :disabled="$isFormLocked"
                    class="w-full mt-1" />
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.statusPsikologis.sebutstatusPsikologis')" class="mt-1" />
            </div>

        </div>
    </x-border-form>

    {{-- Status Mental --}}
    <x-border-form :title="__('Status Mental')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 space-y-4">

            <x-select-input wire:model.live="dataDaftarPoliRJ.anamnesa.statusMental.statusMental" :error="$errors->has('dataDaftarPoliRJ.anamnesa.statusMental.statusMental')"
                :disabled="$isFormLocked" class="w-full mt-1">
                <option value="">-- Pilih Status Mental --</option>
                @foreach ($dataDaftarPoliRJ['anamnesa']['statusMental']['statusMentalOption'] as $statusMentalOption)
                    <option value="{{ $statusMentalOption['statusMental'] }}">
                        {{ $statusMentalOption['statusMental'] }}
                    </option>
                @endforeach
            </x-select-input>

            <div>
                <x-input-label value="Lainnya" />
                <x-text-input wire:model.live="dataDaftarPoliRJ.anamnesa.statusMental.sebutstatusPsikologis"
                    placeholder="Keterangan status mental lainnya" :error="$errors->has('dataDaftarPoliRJ.anamnesa.statusMental.sebutstatusPsikologis')" :disabled="$isFormLocked"
                    class="w-full mt-1" />
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.statusMental.sebutstatusPsikologis')" class="mt-1" />
            </div>

        </div>
    </x-border-form>

</div>
