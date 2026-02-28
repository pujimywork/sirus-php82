<div class="pt-0">
    {{-- TANDA VITAL --}}
    <div>
        <x-input-label for="dataDaftarPoliRJ.pemeriksaan.tandaVital.keadaanUmum" :value="__('Keadaan Umum')" :required="__(false)"
            class="pt-2 sm:text-xl" />

        <div class="mb-2">
            <x-text-input id="dataDaftarPoliRJ.pemeriksaan.tandaVital.keadaanUmum" placeholder="Keadaan Umum"
                class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.keadaanUmum')" :disabled="$isFormLocked"
                wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.keadaanUmum" />
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.keadaanUmum')" class="mt-1" />
        </div>

        <x-input-label for="dataDaftarPoliRJ.pemeriksaan.tandaVital.tingkatKesadaran" :value="__('Tingkat Kesadaran')"
            :required="__(false)" />

        <div class="mt-1">
            <x-select-input wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.tingkatKesadaran" :disabled="$isFormLocked"
                :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.tingkatKesadaran')">
                <option value="">Pilih Tingkat Kesadaran</option>
                @foreach ($dataDaftarPoliRJ['pemeriksaan']['tandaVital']['tingkatKesadaranOptions'] ?? [] as $option)
                    <option value="{{ $option['tingkatKesadaran'] }}">
                        {{ $option['tingkatKesadaran'] }}
                    </option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.tingkatKesadaran')" class="mt-1" />
        </div>

        <x-input-label for="dataDaftarPoliRJ.pemeriksaan.tandaVital.sistolik" :value="__('Tanda Vital')" :required="__(false)"
            class="pt-2 sm:text-xl" />

        <div class="mb-2">
            <x-input-label for="dataDaftarPoliRJ.pemeriksaan.tandaVital.sistolik" :value="__('Tekanan Darah')"
                :required="__(false)" />
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <x-text-input-mou id="dataDaftarPoliRJ.pemeriksaan.tandaVital.sistolik" placeholder="Sistolik"
                        class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.sistolik')" :disabled="$isFormLocked" :mou_label="__('mmHg')"
                        wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.sistolik" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.sistolik')" class="mt-1" />
                </div>
                <div>
                    <x-text-input-mou id="dataDaftarPoliRJ.pemeriksaan.tandaVital.distolik" placeholder="Distolik"
                        class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.distolik')" :disabled="$isFormLocked" :mou_label="__('mmHg')"
                        wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.distolik" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.distolik')" class="mt-1" />
                </div>
            </div>
        </div>

        <div class="mb-2">
            <div class="grid grid-cols-2 gap-2">
                <x-input-label for="dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNadi" :value="__('Frekuensi Nadi')"
                    :required="__(false)" />
                <x-input-label for="dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNafas" :value="__('Frekuensi Nafas')"
                    :required="__(false)" />
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <x-text-input-mou id="dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNadi"
                        placeholder="Frekuensi Nadi" class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNadi')" :disabled="$isFormLocked"
                        :mou_label="__('X/Menit')" wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNadi" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNadi')" class="mt-1" />
                </div>
                <div>
                    <x-text-input-mou id="dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNafas"
                        placeholder="Frekuensi Nafas" class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNafas')" :disabled="$isFormLocked"
                        :mou_label="__('X/Menit')" wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNafas" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNafas')" class="mt-1" />
                </div>
            </div>
        </div>

        <div class="mb-2">
            <div class="grid grid-cols-2 gap-2">
                <x-input-label for="dataDaftarPoliRJ.pemeriksaan.tandaVital.suhu" :value="__('Suhu')"
                    :required="__(false)" />
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <x-text-input-mou id="dataDaftarPoliRJ.pemeriksaan.tandaVital.suhu" placeholder="Suhu"
                        class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.suhu')" :disabled="$isFormLocked" :mou_label="__('°C')"
                        wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.suhu" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.suhu')" class="mt-1" />
                </div>
            </div>
        </div>

        <div class="mb-2">
            <div class="grid grid-cols-2 gap-2">
                <x-input-label for="dataDaftarPoliRJ.pemeriksaan.tandaVital.spo2" :value="__('SPO2')"
                    :required="__(false)" />
                <x-input-label for="dataDaftarPoliRJ.pemeriksaan.tandaVital.gda" :value="__('GDA')"
                    :required="__(false)" />
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <x-text-input-mou id="dataDaftarPoliRJ.pemeriksaan.tandaVital.spo2" placeholder="SPO2"
                        class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.spo2')" :disabled="$isFormLocked" :mou_label="__('%')"
                        wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.spo2" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.spo2')" class="mt-1" />
                </div>
                <div>
                    <x-text-input-mou id="dataDaftarPoliRJ.pemeriksaan.tandaVital.gda" placeholder="GDA"
                        class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.gda')" :disabled="$isFormLocked" :mou_label="__('g/dl')"
                        wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.gda" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.gda')" class="mt-1" />
                </div>
            </div>
        </div>
    </div>

    {{-- NUTRISI --}}
    <div>
        <x-input-label for="dataDaftarPoliRJ.pemeriksaan.nutrisi.bb" :value="__('Nutrisi')" :required="__(false)"
            class="pt-2 sm:text-xl" />

        <div class="grid grid-cols-3 gap-2 pt-2">
            <div class="mb-2">
                <x-input-label for="dataDaftarPoliRJ.pemeriksaan.nutrisi.bb" :value="__('Berat Badan')" :required="__(false)" />
                <x-text-input-mou id="dataDaftarPoliRJ.pemeriksaan.nutrisi.bb" placeholder="Berat Badan"
                    class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.nutrisi.bb')" :disabled="$isFormLocked"
                    wire:model.live="dataDaftarPoliRJ.pemeriksaan.nutrisi.bb" :mou_label="__('Kg')" />
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.nutrisi.bb')" class="mt-1" />
            </div>

            <div class="mb-2">
                <x-input-label for="dataDaftarPoliRJ.pemeriksaan.nutrisi.tb" :value="__('Tinggi Badan')" :required="__(false)" />
                <x-text-input-mou id="dataDaftarPoliRJ.pemeriksaan.nutrisi.tb" placeholder="Tinggi Badan"
                    class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.nutrisi.tb')" :disabled="$isFormLocked"
                    wire:model.live="dataDaftarPoliRJ.pemeriksaan.nutrisi.tb" :mou_label="__('Cm')" />
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.nutrisi.tb')" class="mt-1" />
            </div>

            <div class="mb-2">
                <x-input-label for="dataDaftarPoliRJ.pemeriksaan.nutrisi.imt" :value="__('Index Masa Tubuh')" :required="__(false)" />
                <div class="flex mt-1 ml-2">
                    {{-- Bagian nilai IMT (seperti input kiri) --}}
                    <div
                        class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-l-lg dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                        <span class="text-gray-900 dark:text-gray-100">
                            {{ $dataDaftarPoliRJ['pemeriksaan']['nutrisi']['imt'] ?? '-' }}
                        </span>
                    </div>

                    {{-- Bagian label MOU (seperti input kanan) --}}
                    <div
                        class="px-2 py-2 text-sm font-semibold text-center text-gray-500 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg w-28 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                        Kg/M2
                    </div>
                </div>
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.nutrisi.imt')" class="mt-1" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2 pt-2">
            <div class="mb-2">
                <x-input-label for="dataDaftarPoliRJ.pemeriksaan.nutrisi.lk" :value="__('Lingkar Kepala')" :required="__(false)" />
                <x-text-input-mou id="dataDaftarPoliRJ.pemeriksaan.nutrisi.lk" placeholder="Lingkar Kepala"
                    class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.nutrisi.lk')" :disabled="$isFormLocked"
                    wire:model.live="dataDaftarPoliRJ.pemeriksaan.nutrisi.lk" :mou_label="__('Cm')" />
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.nutrisi.lk')" class="mt-1" />
            </div>

            <div class="mb-2">
                <x-input-label for="dataDaftarPoliRJ.pemeriksaan.nutrisi.lila" :value="__('Lingkar Lengan Atas')" :required="__(false)" />
                <x-text-input-mou id="dataDaftarPoliRJ.pemeriksaan.nutrisi.lila" placeholder="Lingkar Lengan Atas"
                    class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.nutrisi.lila')" :disabled="$isFormLocked"
                    wire:model.live="dataDaftarPoliRJ.pemeriksaan.nutrisi.lila" :mou_label="__('Cm')" />
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.nutrisi.lila')" class="mt-1" />
            </div>
        </div>
    </div>

    {{-- FUNGSIONAL --}}
    <div>
        <x-input-label for="dataDaftarPoliRJ.pemeriksaan.fungsional.alatBantu" :value="__('Fungsional')" :required="__(false)"
            class="pt-2 sm:text-xl" />

        <div class="grid grid-cols-3 gap-2 pt-2">
            <div class="mb-2">
                <x-input-label for="dataDaftarPoliRJ.pemeriksaan.fungsional.alatBantu" :value="__('Alat Bantu')"
                    :required="__(false)" />
                <x-text-input id="dataDaftarPoliRJ.pemeriksaan.fungsional.alatBantu" placeholder="Alat Bantu"
                    class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.fungsional.alatBantu')" :disabled="$isFormLocked"
                    wire:model.live="dataDaftarPoliRJ.pemeriksaan.fungsional.alatBantu" />
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.fungsional.alatBantu')" class="mt-1" />
            </div>

            <div class="mb-2">
                <x-input-label for="dataDaftarPoliRJ.pemeriksaan.fungsional.prothesa" :value="__('Prothesa')"
                    :required="__(false)" />
                <x-text-input id="dataDaftarPoliRJ.pemeriksaan.fungsional.prothesa" placeholder="Prothesa"
                    class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.fungsional.prothesa')" :disabled="$isFormLocked"
                    wire:model.live="dataDaftarPoliRJ.pemeriksaan.fungsional.prothesa" />
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.fungsional.prothesa')" class="mt-1" />
            </div>

            <div class="mb-2">
                <x-input-label for="dataDaftarPoliRJ.pemeriksaan.fungsional.cacatTubuh" :value="__('Cacat Tubuh')"
                    :required="__(false)" />
                <x-text-input id="dataDaftarPoliRJ.pemeriksaan.fungsional.cacatTubuh" placeholder="Cacat Tubuh"
                    class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.fungsional.cacatTubuh')" :disabled="$isFormLocked"
                    wire:model.live="dataDaftarPoliRJ.pemeriksaan.fungsional.cacatTubuh" />
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.fungsional.cacatTubuh')" class="mt-1" />
            </div>
        </div>
    </div>

    {{-- SUSPEK AKIBAT KERJA --}}
    <div class="mb-2">
        <x-input-label for="dataDaftarPoliRJ.pemeriksaan.suspekAkibatKerja.keteranganSuspekAkibatKerja"
            :value="__('Suspek Penyakit Akibat Kecelakaan Kerja')" :required="__(false)" />

        <div class="grid grid-cols-3 gap-2 mb-2">
            @foreach ($dataDaftarPoliRJ['pemeriksaan']['suspekAkibatKerja']['suspekAkibatKerjaOptions'] ?? [] as $suspekAkibatKerjaOption)
                <x-radio-button :label="__($suspekAkibatKerjaOption['suspekAkibatKerja'])" :value="$suspekAkibatKerjaOption['suspekAkibatKerja']" name="suspekAkibatKerja"
                    wire:model.live="suspekAkibatKerja" :disabled="$isFormLocked" />
            @endforeach

            <x-text-input id="keteranganSuspekAkibatKerja" placeholder="Keterangan" class="mt-1 ml-2"
                :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.suspekAkibatKerja.keteranganSuspekAkibatKerja')" :disabled="$isFormLocked ?? false"
                wire:model.live="dataDaftarPoliRJ.pemeriksaan.suspekAkibatKerja.keteranganSuspekAkibatKerja" />
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.suspekAkibatKerja.keteranganSuspekAkibatKerja')" class="mt-1" />
        </div>
    </div>
</div>
