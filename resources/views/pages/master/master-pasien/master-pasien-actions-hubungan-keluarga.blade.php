<x-border-form :title="__('Hubungan Keluarga')" :align="__('start')" :bgcolor="__('bg-white')">
    <div class="space-y-5">
        {{-- Subsection: Penanggung Jawab --}}
        <div class="p-4 rounded-lg bg-yellow-50">
            <h4 class="mb-3 font-semibold text-yellow-800">Penanggung Jawab</h4>
            <div class="grid grid-cols-1 gap-4">
                {{-- Nama Penanggung Jawab + No HP --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <x-input-label value="Nama Penanggung Jawab" :required="true" />
                        <x-text-input wire:model.live="dataPasien.pasien.hubungan.namaPenanggungJawab" :error="$errors->has('dataPasien.pasien.hubungan.namaPenanggungJawab')"
                            class="w-full mt-1" style="text-transform:uppercase" />
                        <x-input-error :messages="$errors->get('dataPasien.pasien.hubungan.namaPenanggungJawab')" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <x-input-label value="Kode Negara" />
                            <x-text-input wire:model.live="dataPasien.pasien.hubungan.kodenegaraPenanggungJawab"
                                class="w-full mt-1" />
                        </div>
                        <div>
                            <x-input-label value="No HP" :required="true" />
                            <x-text-input wire:model.live="dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab"
                                :error="$errors->has('dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab')" class="mt-1" />
                        </div>
                    </div>
                </div>

                {{-- Hubungan dengan Pasien --}}
                <div class="sm:w-1/2">
                    <x-input-label value="Hubungan dengan Pasien" :required="true" />
                    <x-select-input wire:model.live="dataPasien.pasien.hubungan.hubunganDgnPasien.hubunganDgnPasienId"
                        :error="$errors->has('dataPasien.pasien.hubungan.hubunganDgnPasien.hubunganDgnPasienId')">
                        <option value="">-- Pilih Hubungan --</option>
                        @foreach ($hubunganDgnPasienOptions as $option)
                            <option value="{{ $option['id'] }}"
                                {{ ($dataPasien['pasien']['hubungan']['hubunganDgnPasien']['hubunganDgnPasienId'] ?? '') == $option['id'] ? 'selected' : '' }}>
                                {{ $option['id'] }}. {{ $option['desc'] }}
                            </option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('dataPasien.pasien.hubungan.hubunganDgnPasien.hubunganDgnPasienId')" class="mt-1" />
                </div>
            </div>
        </div>

        {{-- Subsection: Ayah --}}
        <div>
            <h4 class="mb-3 font-semibold text-gray-700">Data Ayah</h4>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-input-label value="Nama Ayah" :required="true" />
                    <x-text-input wire:model.live="dataPasien.pasien.hubungan.namaAyah" :error="$errors->has('dataPasien.pasien.hubungan.namaAyah')"
                        class="w-full mt-1" style="text-transform:uppercase" />
                    <x-input-error :messages="$errors->get('dataPasien.pasien.hubungan.namaAyah')" class="mt-1" />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <x-input-label value="Kode Negara" />
                        <x-text-input wire:model.live="dataPasien.pasien.hubungan.kodenegaraAyah" class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label value="No HP Ayah" :required="true" />
                        <x-text-input wire:model.live="dataPasien.pasien.hubungan.nomerTelponSelulerAyah"
                            :error="$errors->has('dataPasien.pasien.hubungan.nomerTelponSelulerAyah')" class="w-full mt-1" />
                        <x-input-error :messages="$errors->get('dataPasien.pasien.hubungan.nomerTelponSelulerAyah')" class="mt-1" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Subsection: Ibu --}}
        <div>
            <h4 class="mb-3 font-semibold text-gray-700">Data Ibu</h4>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-input-label value="Nama Ibu" :required="true" />
                    <x-text-input wire:model.live="dataPasien.pasien.hubungan.namaIbu" :error="$errors->has('dataPasien.pasien.hubungan.namaIbu')"
                        class="w-full mt-1" style="text-transform:uppercase" />
                    <x-input-error :messages="$errors->get('dataPasien.pasien.hubungan.namaIbu')" class="mt-1" />
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <x-input-label value="Kode Negara" />
                        <x-text-input wire:model.live="dataPasien.pasien.hubungan.kodenegaraIbu" class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label value="No HP Ibu" :required="true" />
                        <x-text-input wire:model.live="dataPasien.pasien.hubungan.nomerTelponSelulerIbu"
                            :error="$errors->has('dataPasien.pasien.hubungan.nomerTelponSelulerIbu')" class="w-full mt-1" />
                        <x-input-error :messages="$errors->get('dataPasien.pasien.hubungan.nomerTelponSelulerIbu')" class="mt-1" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-border-form>
