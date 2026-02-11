<x-border-form :title="__('Data Sosial')" :align="__('start')" :bgcolor="__('bg-white')">
    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-5">
            {{-- Jenis Kelamin --}}
            <div>
                <x-input-label value="Jenis Kelamin" :required="true" />
                <x-select-input wire:model.live="dataPasien.pasien.jenisKelamin.jenisKelaminId" :error="$errors->has('dataPasien.pasien.jenisKelamin.jenisKelaminId')">
                    <option value="">-- Pilih Jenis Kelamin --</option>
                    @foreach ($jenisKelaminOptions as $option)
                        <option value="{{ $option['id'] }}"
                            {{ ($dataPasien['pasien']['jenisKelamin']['jenisKelaminId'] ?? '') == $option['id'] ? 'selected' : '' }}>
                            {{ $option['id'] }}. {{ $option['desc'] }}
                        </option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('dataPasien.pasien.jenisKelamin.jenisKelaminId')" class="mt-1" />
            </div>

            {{-- Agama --}}
            <div>
                <x-input-label value="Agama" :required="true" />
                <x-select-input wire:model.live="dataPasien.pasien.agama.agamaId" :error="$errors->has('dataPasien.pasien.agama.agamaId')">
                    <option value="">-- Pilih Agama --</option>
                    @foreach ($agamaOptions as $option)
                        <option value="{{ $option['id'] }}"
                            {{ ($dataPasien['pasien']['agama']['agamaId'] ?? '') == $option['id'] ? 'selected' : '' }}>
                            {{ $option['id'] }}. {{ $option['desc'] }}
                        </option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('dataPasien.pasien.agama.agamaId')" class="mt-1" />
            </div>

            {{-- Status Perkawinan --}}
            <div>
                <x-input-label value="Status Perkawinan" :required="true" />
                <x-select-input wire:model.live="dataPasien.pasien.statusPerkawinan.statusPerkawinanId"
                    :error="$errors->has('dataPasien.pasien.statusPerkawinan.statusPerkawinanId')">
                    <option value="">-- Pilih Status Perkawinan --</option>
                    @foreach ($statusPerkawinanOptions as $option)
                        <option value="{{ $option['id'] }}"
                            {{ ($dataPasien['pasien']['statusPerkawinan']['statusPerkawinanId'] ?? '') == $option['id'] ? 'selected' : '' }}>
                            {{ $option['id'] }}. {{ $option['desc'] }}
                        </option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('dataPasien.pasien.statusPerkawinan.statusPerkawinanId')" class="mt-1" />
            </div>

            {{-- Pendidikan --}}
            <div>
                <x-input-label value="Pendidikan" :required="true" />
                <x-select-input wire:model.live="dataPasien.pasien.pendidikan.pendidikanId" :error="$errors->has('dataPasien.pasien.pendidikan.pendidikanId')">
                    <option value="">-- Pilih Pendidikan --</option>
                    @foreach ($pendidikanOptions as $option)
                        <option value="{{ $option['id'] }}"
                            {{ ($dataPasien['pasien']['pendidikan']['pendidikanId'] ?? '') == $option['id'] ? 'selected' : '' }}>
                            {{ $option['id'] }}. {{ $option['desc'] }}
                        </option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('dataPasien.pasien.pendidikan.pendidikanId')" class="mt-1" />
            </div>

            {{-- Pekerjaan --}}
            <div>
                <x-input-label value="Pekerjaan" :required="true" />
                <x-select-input wire:model.live="dataPasien.pasien.pekerjaan.pekerjaanId" :error="$errors->has('dataPasien.pasien.pekerjaan.pekerjaanId')">
                    <option value="">-- Pilih Pekerjaan --</option>
                    @foreach ($pekerjaanOptions as $option)
                        <option value="{{ $option['id'] }}"
                            {{ ($dataPasien['pasien']['pekerjaan']['pekerjaanId'] ?? '') == $option['id'] ? 'selected' : '' }}>
                            {{ $option['id'] }}. {{ $option['desc'] }}
                        </option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('dataPasien.pasien.pekerjaan.pekerjaanId')" class="mt-1" />
            </div>
        </div>
    </div>
</x-border-form>
