<div class="w-full mb-1">
    <div class="pt-0">
        <x-input-label for="dataDaftarPoliRJ.pemeriksaan.penunjang" :value="__('Penunjang')" :required="__(false)"
            class="pt-2 sm:text-xl" />

        <div class="mb-2">
            <x-input-label for="dataDaftarPoliRJ.pemeriksaan.penunjang" :value="__('Pemeriksaan Penunjang Lab / Foto / EKG / Lain-lain')" :required="__(false)" />

            <x-textarea id="dataDaftarPoliRJ.pemeriksaan.penunjang" placeholder="Penunjang" class="mt-1 ml-2"
                :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.penunjang')" :disabled="$isFormLocked" wire:model.live="dataDaftarPoliRJ.pemeriksaan.penunjang"
                rows="3" />
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.penunjang')" class="mt-1" />
        </div>
    </div>

    <div class="pt-0">
        <livewire:pages::components.rekam-medis.rekam-medis.penunjang.laboratorium-display.laboratorium-display
            :regNo="$dataDaftarPoliRJ['regNo'] ?? ''" wire:key="emr-rj.laboratorium-display-{{ $dataDaftarPoliRJ['regNo'] ?? 'new' }}" />
    </div>

    <div class="pt-0">
        <livewire:pages::components.rekam-medis.rekam-medis.penunjang.radiologi-display.radiologi-display
            :regNo="$dataDaftarPoliRJ['regNo'] ?? ''" wire:key="emr-rj.radiologi-display-{{ $dataDaftarPoliRJ['regNo'] ?? 'new' }}" />
    </div>

    <div class="pt-0">
        <livewire:pages::components.rekam-medis.rekam-medis.penunjang.upload-penunjang-display.upload-penunjang-display
            :regNo="$dataDaftarPoliRJ['regNo'] ?? ''" wire:key="emr-rj.upload-penunjang-display-{{ $dataDaftarPoliRJ['regNo'] ?? 'new' }}" />
    </div>
</div>
