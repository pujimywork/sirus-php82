<x-border-form :title="__('Data Budaya')" :align="__('start')" :bgcolor="__('bg-white')">
    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-5">
            {{-- Golongan Darah --}}
            <div>
                <x-input-label value="Golongan Darah" />
                <x-select-input wire:model.live="dataPasien.pasien.golonganDarah.golonganDarahId" :error="$errors->has('dataPasien.pasien.golonganDarah.golonganDarahId')">
                    <option value="">-- Pilih Golongan Darah --</option>
                    @foreach ($golonganDarahOptions as $option)
                        <option value="{{ $option['id'] }}"
                            {{ ($dataPasien['pasien']['golonganDarah']['golonganDarahId'] ?? '') == $option['id'] ? 'selected' : '' }}>
                            {{ $option['id'] }}. {{ $option['desc'] }}
                        </option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('dataPasien.pasien.golonganDarah.golonganDarahId')" class="mt-1" />
            </div>

            {{-- Kewarganegaraan --}}
            <div>
                <x-input-label value="Kewarganegaraan" />
                <x-text-input wire:model.live="dataPasien.pasien.kewarganegaraan" :error="$errors->has('dataPasien.pasien.kewarganegaraan')" class="w-full mt-1" />
                <x-input-error :messages="$errors->get('dataPasien.pasien.kewarganegaraan')" class="mt-1" />
            </div>

            {{-- Suku --}}
            <div>
                <x-input-label value="Suku" />
                <x-text-input wire:model.live="dataPasien.pasien.suku" :error="$errors->has('dataPasien.pasien.suku')" class="w-full mt-1" />
                <x-input-error :messages="$errors->get('dataPasien.pasien.suku')" class="mt-1" />
            </div>

            {{-- Bahasa --}}
            <div>
                <x-input-label value="Bahasa" />
                <x-text-input wire:model.live="dataPasien.pasien.bahasa" :error="$errors->has('dataPasien.pasien.bahasa')"
                    placeholder="Bahasa yang digunakan" class="w-full mt-1" />
                <x-input-error :messages="$errors->get('dataPasien.pasien.bahasa')" class="mt-1" />
            </div>

            {{-- Status --}}
            <div>
                <x-input-label value="Status" />
                <x-select-input wire:model.live="dataPasien.pasien.status.statusId" :error="$errors->has('dataPasien.pasien.status.statusId')">
                    <option value="">-- Pilih Status --</option>
                    @foreach ($statusOptions as $option)
                        <option value="{{ $option['id'] }}"
                            {{ ($dataPasien['pasien']['status']['statusId'] ?? '') == $option['id'] ? 'selected' : '' }}>
                            {{ $option['id'] }}. {{ $option['desc'] }}
                        </option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('dataPasien.pasien.status.statusId')" class="mt-1" />
            </div>
        </div>
    </div>
</x-border-form>
