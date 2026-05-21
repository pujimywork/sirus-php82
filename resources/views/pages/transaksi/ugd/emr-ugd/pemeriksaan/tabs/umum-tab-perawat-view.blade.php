{{-- pages/transaksi/ugd/emr-ugd/pemeriksaan/tabs/umum-tab-perawat-view.blade.php --}}
<div class="space-y-4">

    {{-- TANDA VITAL --}}
    <x-border-form :title="__('Tanda Vital')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="space-y-4">

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

            {{-- PENGKAJIAN PRIMER (ABCD) — A: Jalan Nafas, B: Pernafasan + Gerak Dada, C: Sirkulasi, D: Disability --}}
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <x-input-label value="A — Jalan Nafas (Airway)" />
                    <x-select-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.jalanNafas.jalanNafas"
                        :disabled="$isFormLocked" class="w-full mt-1">
                        <option value="">-- Pilih --</option>
                        @foreach ($dataDaftarUGD['pemeriksaan']['tandaVital']['jalanNafas']['jalanNafasOptions'] ?? [] as $opt)
                            <option value="{{ $opt['jalanNafas'] }}">{{ $opt['jalanNafas'] }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label value="B — Pernafasan (Breathing)" />
                    <x-select-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.pernafasan.pernafasan"
                        :disabled="$isFormLocked" class="w-full mt-1">
                        <option value="">-- Pilih --</option>
                        @foreach ($dataDaftarUGD['pemeriksaan']['tandaVital']['pernafasan']['pernafasanOptions'] ?? [] as $opt)
                            <option value="{{ $opt['pernafasan'] }}">{{ $opt['pernafasan'] }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label value="Gerak Dada" />
                    <x-select-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.gerakDada.gerakDada"
                        :disabled="$isFormLocked" class="w-full mt-1">
                        <option value="">-- Pilih --</option>
                        @foreach ($dataDaftarUGD['pemeriksaan']['tandaVital']['gerakDada']['gerakDadaOptions'] ?? [] as $opt)
                            <option value="{{ $opt['gerakDada'] }}">{{ $opt['gerakDada'] }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label value="C — Sirkulasi (Circulation)" />
                    <x-select-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.sirkulasi.sirkulasi"
                        :disabled="$isFormLocked" class="w-full mt-1">
                        <option value="">-- Pilih --</option>
                        @foreach ($dataDaftarUGD['pemeriksaan']['tandaVital']['sirkulasi']['sirkulasiOptions'] ?? [] as $opt)
                            <option value="{{ $opt['sirkulasi'] }}">{{ $opt['sirkulasi'] }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div class="sm:col-span-2">
                    <x-input-label value="D — Disability (Tingkat Kesadaran Neurologis)" />
                    <x-select-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.disability.disability"
                        :disabled="$isFormLocked" class="w-full mt-1">
                        <option value="">-- Pilih --</option>
                        @foreach ($dataDaftarUGD['pemeriksaan']['tandaVital']['disability']['disabilityOptions'] ?? [] as $opt)
                            <option value="{{ $opt['disability'] }}">{{ $opt['disability'] }}</option>
                        @endforeach
                    </x-select-input>
                </div>
            </div>

            {{-- GCS (Eye / Verbal / Motor / Total) --}}
            <div>
                <x-input-label value="GCS (Glasgow Coma Scale)" />
                <div class="grid grid-cols-2 gap-2 mt-1 sm:grid-cols-4">
                    <div>
                        <x-select-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.e"
                            :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.e')" :disabled="$isFormLocked" class="w-full">
                            <option value="">Eye (E)</option>
                            <option value="4">E4 — Spontan</option>
                            <option value="3">E3 — Suara</option>
                            <option value="2">E2 — Nyeri</option>
                            <option value="1">E1 — Tidak ada</option>
                        </x-select-input>
                        <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.e')" class="mt-1" />
                    </div>
                    <div>
                        <x-select-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.v"
                            :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.v')" :disabled="$isFormLocked" class="w-full">
                            <option value="">Verbal (V)</option>
                            <option value="5">V5 — Orientasi baik</option>
                            <option value="4">V4 — Bingung</option>
                            <option value="3">V3 — Kata tdk pas</option>
                            <option value="2">V2 — Suara erangan</option>
                            <option value="1">V1 — Tidak ada</option>
                        </x-select-input>
                        <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.v')" class="mt-1" />
                    </div>
                    <div>
                        <x-select-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.m"
                            :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.m')" :disabled="$isFormLocked" class="w-full">
                            <option value="">Motor (M)</option>
                            <option value="6">M6 — Ikut perintah</option>
                            <option value="5">M5 — Lokalisasi nyeri</option>
                            <option value="4">M4 — Menghindar nyeri</option>
                            <option value="3">M3 — Fleksi abnormal</option>
                            <option value="2">M2 — Ekstensi abnormal</option>
                            <option value="1">M1 — Tidak ada</option>
                        </x-select-input>
                        <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.m')" class="mt-1" />
                    </div>
                    <div>
                        <x-text-input :value="$dataDaftarUGD['pemeriksaan']['tandaVital']['gcs'] ?? ''" :disabled="true"
                            placeholder="Total (3-15)" class="w-full font-semibold text-center" />
                    </div>
                </div>
            </div>

            {{-- Grid tanda vital --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <x-input-label value="Sistolik (mmHg)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.sistolik" placeholder=""
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.sistolik')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.sistolik')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Diastolik (mmHg)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.distolik" placeholder=""
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.distolik')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.distolik')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Nadi (x/mnt)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.frekuensiNadi" placeholder=""
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.frekuensiNadi')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.frekuensiNadi')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Nafas (x/mnt)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.frekuensiNafas" placeholder=""
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.frekuensiNafas')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.frekuensiNafas')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Suhu (°C)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.suhu" placeholder=""
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.suhu')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.suhu')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="SPO2 (%)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.spo2" placeholder=""
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.spo2')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.spo2')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="GDA (g/dl)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.tandaVital.gda" placeholder=""
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.tandaVital.gda')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.tandaVital.gda')" class="mt-1" />
                </div>
            </div>

        </div>
    </x-border-form>

    {{-- NUTRISI --}}
    <x-border-form :title="__('Nutrisi')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="space-y-4">

            {{-- BB, TB, IMT --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label value="Berat Badan (Kg)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.nutrisi.bb" placeholder=""
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.nutrisi.bb')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.nutrisi.bb')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Tinggi Badan (Cm)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.nutrisi.tb" placeholder=""
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.nutrisi.tb')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.nutrisi.tb')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Index Masa Tubuh (Kg/M²)" class="whitespace-nowrap" />
                    {{-- IMT readonly, dihitung otomatis via hitungIMT() di server saat BB/TB update --}}
                    <div class="flex mt-1">
                        <div
                            class="w-full px-3 py-2 text-base text-gray-900 bg-gray-100 border border-gray-300 rounded-l-lg dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                            {{ $dataDaftarUGD['pemeriksaan']['nutrisi']['imt'] ?? '-' }}
                        </div>
                        <div
                            class="px-3 py-2 text-sm font-semibold text-center text-gray-500 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg whitespace-nowrap dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                            Kg/M²
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.nutrisi.imt')" class="mt-1" />
                </div>
            </div>

            {{-- Lingkar Kepala & LILA --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label value="Lingkar Kepala (Cm)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.nutrisi.lk" placeholder=""
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.nutrisi.lk')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.nutrisi.lk')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Lingkar Lengan Atas (Cm)" class="whitespace-nowrap" />
                    <x-text-input wire:model.live="dataDaftarUGD.pemeriksaan.nutrisi.lila" placeholder=""
                        :error="$errors->has('dataDaftarUGD.pemeriksaan.nutrisi.lila')" :disabled="$isFormLocked" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.nutrisi.lila')" class="mt-1" />
                </div>
            </div>

        </div>
    </x-border-form>

</div>
