{{-- pages/transaksi/ugd/emr-ugd/pemeriksaan/tabs/umum-tab-perawat-view.blade.php --}}
<div class="space-y-4">

    {{-- TANDA VITAL --}}
    <x-border-form :title="__('Tanda Vital')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 space-y-4">

            {{-- Keadaan Umum --}}
            <div>
                <x-input-label value="Keadaan Umum" />
                <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.keadaanUmum"
                    placeholder="Keadaan Umum" :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.keadaanUmum')" :disabled="$isFormLocked" class="w-full mt-1" />
                <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.keadaanUmum')" class="mt-1" />
            </div>

            {{-- Tingkat Kesadaran --}}
            <div>
                <x-input-label value="Tingkat Kesadaran" />
                <x-select-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.tingkatKesadaran" :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.tingkatKesadaran')"
                    :disabled="$isFormLocked" class="w-full mt-1">
                    <option value="">-- Pilih Tingkat Kesadaran --</option>
                    @foreach ($dataDaftarUGD['pemeriksaan']['tandaVital']['tingkatKesadaranOptions'] ?? [] as $option)
                        <option value="{{ $option['tingkatKesadaran'] }}">{{ $option['tingkatKesadaran'] }}</option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.tingkatKesadaran')" class="mt-1" />
            </div>

            {{-- Grid tanda vital --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label value="Sistolik (mmHg)" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.sistolik" placeholder="120"
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.sistolik')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.sistolik')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Diastolik (mmHg)" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.distolik" placeholder="80"
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.distolik')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.distolik')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Frekuensi Nadi (x/mnt)" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.frekuensiNadi" placeholder="80"
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.frekuensiNadi')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.frekuensiNadi')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Frekuensi Nafas (x/mnt)" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.frekuensiNafas" placeholder="20"
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.frekuensiNafas')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.frekuensiNafas')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Suhu (°C)" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.suhu" placeholder="36.5"
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.suhu')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.suhu')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="SPO2 (%)" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.spo2" placeholder="98"
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.spo2')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.spo2')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="GDA (g/dl)" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.gda" placeholder="100"
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.gda')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.gda')" class="mt-1" />
                </div>
            </div>

        </div>
    </x-border-form>

    {{-- NUTRISI --}}
    <x-border-form :title="__('Nutrisi')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 space-y-4">

            {{-- BB, TB, IMT --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label value="Berat Badan (Kg)" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.nutrisi.bb" placeholder="60"
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.nutrisi.bb')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.nutrisi.bb')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Tinggi Badan (Cm)" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.nutrisi.tb" placeholder="165"
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.nutrisi.tb')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.nutrisi.tb')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Index Masa Tubuh (Kg/M²)" />
                    {{-- IMT readonly, dihitung otomatis --}}
                    <div class="flex mt-1">
                        <div
                            class="w-full px-3 py-2 text-sm text-gray-900 bg-gray-100 border border-gray-300 rounded-l-lg dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                            {{ $dataDaftarUGD['pemeriksaan']['nutrisi']['imt'] ?? '-' }}
                        </div>
                        <div
                            class="px-3 py-2 text-xs font-semibold text-center text-gray-500 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg whitespace-nowrap dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                            Kg/M²
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.nutrisi.imt')" class="mt-1" />
                </div>
            </div>

            {{-- Lingkar Kepala & LILA --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label value="Lingkar Kepala (Cm)" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.nutrisi.lk" placeholder="35"
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.nutrisi.lk')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.nutrisi.lk')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Lingkar Lengan Atas (Cm)" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.nutrisi.lila" placeholder="25"
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.nutrisi.lila')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.nutrisi.lila')" class="mt-1" />
                </div>
            </div>

        </div>
    </x-border-form>

</div>
