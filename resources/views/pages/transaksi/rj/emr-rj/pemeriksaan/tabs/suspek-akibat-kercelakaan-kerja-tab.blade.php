<x-border-form :title="__('Suspek Penyakit Akibat Kecelakaan Kerja')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-start">

        {{-- Select --}}
        <div class="sm:col-span-1">
            <x-input-label value="Suspek" />
            <x-select-input wire:model.live="suspekAkibatKerja" :disabled="$isFormLocked" class="w-full mt-1">
                @foreach ($dataDaftarPoliRJ['pemeriksaan']['suspekAkibatKerja']['suspekAkibatKerjaOptions'] ?? [] as $suspekAkibatKerjaOption)
                    <option value="{{ $suspekAkibatKerjaOption['suspekAkibatKerja'] }}">
                        {{ $suspekAkibatKerjaOption['suspekAkibatKerja'] }}
                    </option>
                @endforeach
            </x-select-input>
        </div>

        {{-- Keterangan --}}
        <div class="sm:col-span-2">
            <x-input-label value="Keterangan" />
            <x-text-input id="keteranganSuspekAkibatKerja"
                wire:model.live="dataDaftarPoliRJ.pemeriksaan.suspekAkibatKerja.keteranganSuspekAkibatKerja"
                placeholder="Keterangan" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.suspekAkibatKerja.keteranganSuspekAkibatKerja')" :disabled="$isFormLocked" class="w-full mt-1" />
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.suspekAkibatKerja.keteranganSuspekAkibatKerja')" class="mt-1" />
        </div>

    </div>
</x-border-form>
