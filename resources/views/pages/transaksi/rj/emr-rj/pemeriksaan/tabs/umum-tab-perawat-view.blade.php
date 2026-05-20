<div class="space-y-4">

    {{-- TANDA VITAL --}}
    <x-border-form :title="__('Tanda Vital')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="space-y-4">

            {{-- Keadaan Umum --}}
            <div>
                <x-input-label value="Keadaan Umum" />
                <x-text-input wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.keadaanUmum"
                    placeholder="Keadaan Umum" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.keadaanUmum')" :disabled="$isFormLocked" class="w-full mt-1" />
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.keadaanUmum')" class="mt-1" />
            </div>

            {{-- Tingkat Kesadaran --}}
            <div>
                <x-input-label value="Tingkat Kesadaran" />
                <x-select-input wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.tingkatKesadaran"
                    :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.tingkatKesadaran')" :disabled="$isFormLocked" class="w-full mt-1">
                    <option value="">-- Pilih Tingkat Kesadaran --</option>
                    @foreach ($dataDaftarPoliRJ['pemeriksaan']['tandaVital']['tingkatKesadaranOptions'] ?? [] as $option)
                        <option value="{{ $option['tingkatKesadaran'] }}">{{ $option['tingkatKesadaran'] }}</option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.tingkatKesadaran')" class="mt-1" />
            </div>

            {{-- Grid tanda vital --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <x-input-label value="Sistolik (mmHg)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.sistolik" placeholder=""
                        :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.sistolik')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.sistolik')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Diastolik (mmHg)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.distolik" placeholder=""
                        :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.distolik')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.distolik')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Nadi (x/mnt)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNadi"
                        placeholder="" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNadi')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNadi')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Nafas (x/mnt)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNafas"
                        placeholder="" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNafas')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNafas')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Suhu (°C)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.suhu" placeholder=""
                        :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.suhu')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.suhu')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="SPO2 (%)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.spo2" placeholder=""
                        :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.spo2')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.spo2')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="GDA (g/dl)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarPoliRJ.pemeriksaan.tandaVital.gda" placeholder=""
                        :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.tandaVital.gda')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.gda')" class="mt-1" />
                </div>
            </div>

        </div>
    </x-border-form>

    {{-- NUTRISI --}}
    {{-- Alpine local state untuk BB/TB/LK/LILA → race-free (digit terakhir tidak hilang).
         IMT dihitung client-side (instant) sekaligus disinkron ke server via $wire.set(.., false)
         supaya muat di payload Simpan tanpa roundtrip per-keystroke. --}}
    <x-border-form :title="__('Nutrisi')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="space-y-4"
            x-data="{
                bb: @js($dataDaftarPoliRJ['pemeriksaan']['nutrisi']['bb'] ?? ''),
                tb: @js($dataDaftarPoliRJ['pemeriksaan']['nutrisi']['tb'] ?? ''),
                lk: @js($dataDaftarPoliRJ['pemeriksaan']['nutrisi']['lk'] ?? ''),
                lila: @js($dataDaftarPoliRJ['pemeriksaan']['nutrisi']['lila'] ?? ''),
                get imt() {
                    const b = parseFloat(this.bb) || 0;
                    const t = parseFloat(this.tb) || 0;
                    if (t <= 0) return '-';
                    const tbM = t / 100;
                    return (b / (tbM * tbM)).toFixed(2);
                },
                syncImt() {
                    const v = this.imt === '-' ? 0 : parseFloat(this.imt);
                    $wire.set('dataDaftarPoliRJ.pemeriksaan.nutrisi.imt', v, false);
                },
            }">

            {{-- BB, TB, IMT --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label value="Berat Badan (Kg)" class="whitespace-nowrap" />
                    <x-text-input x-model="bb"
                        x-on:blur="$wire.set('dataDaftarPoliRJ.pemeriksaan.nutrisi.bb', bb, false); syncImt();"
                        placeholder="" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.nutrisi.bb')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.nutrisi.bb')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Tinggi Badan (Cm)" class="whitespace-nowrap" />
                    <x-text-input x-model="tb"
                        x-on:blur="$wire.set('dataDaftarPoliRJ.pemeriksaan.nutrisi.tb', tb, false); syncImt();"
                        placeholder="" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.nutrisi.tb')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.nutrisi.tb')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Index Masa Tubuh (Kg/M²)" class="whitespace-nowrap" />
                    {{-- IMT readonly, computed instan dari Alpine getter --}}
                    <div class="flex mt-1">
                        <div
                            class="w-full px-3 py-2 text-base text-gray-900 bg-gray-100 border border-gray-300 rounded-l-lg dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
                            x-text="imt">
                        </div>
                        <div
                            class="px-3 py-2 text-sm font-semibold text-center text-gray-500 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg whitespace-nowrap dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                            Kg/M²
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.nutrisi.imt')" class="mt-1" />
                </div>
            </div>

            {{-- Lingkar Kepala & LILA --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label value="Lingkar Kepala (Cm)" class="whitespace-nowrap" />
                    <x-text-input x-model="lk"
                        x-on:blur="$wire.set('dataDaftarPoliRJ.pemeriksaan.nutrisi.lk', lk, false)"
                        placeholder="" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.nutrisi.lk')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.nutrisi.lk')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Lingkar Lengan Atas (Cm)" class="whitespace-nowrap" />
                    <x-text-input x-model="lila"
                        x-on:blur="$wire.set('dataDaftarPoliRJ.pemeriksaan.nutrisi.lila', lila, false)"
                        placeholder="" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.nutrisi.lila')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.nutrisi.lila')" class="mt-1" />
                </div>
            </div>

        </div>
    </x-border-form>

</div>
