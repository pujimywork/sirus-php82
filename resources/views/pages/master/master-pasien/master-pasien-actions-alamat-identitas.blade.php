<x-border-form :title="__('Alamat')" :align="__('start')" :bgcolor="__('bg-white')">
    <div class="mt-16 space-y-5">
        {{-- Alamat --}}
        <div>
            <x-input-label value="Alamat" :required="true" />
            <x-textarea wire:model.live="dataPasien.pasien.identitas.alamat" :error="$errors->has('dataPasien.pasien.identitas.alamat')" class="w-full mt-1"
                rows="2" />
            <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.alamat')" class="mt-1" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            {{-- RT --}}
            <div>
                <x-input-label value="RT" :required="true" />
                <x-text-input wire:model.live="dataPasien.pasien.identitas.rt" placeholder="3 digit" :error="$errors->has('dataPasien.pasien.identitas.rt')"
                    class="w-full mt-1" />
                <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.rt')" class="mt-1" />
            </div>

            {{-- RW --}}
            <div>
                <x-input-label value="RW" :required="true" />
                <x-text-input wire:model.live="dataPasien.pasien.identitas.rw" placeholder="3 digit" :error="$errors->has('dataPasien.pasien.identitas.rw')"
                    class="w-full mt-1" />
                <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.rw')" class="mt-1" />
            </div>

            {{-- Kode Pos --}}
            <div>
                <x-input-label value="Kode Pos" />
                <x-text-input wire:model.live="dataPasien.pasien.identitas.kodepos" class="w-full mt-1" />
            </div>
        </div>

        {{-- Alamat Lengkap --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-1">
            {{-- Desa --}}
            <div>
                <livewire:lov.desa.lov-desa target="desa_identitas" :propinsiId="$dataPasien['pasien']['identitas']['propinsiId'] ?? null" :kotaId="$dataPasien['pasien']['identitas']['kotaId'] ?? null"
                    :initialDesaId="$dataPasien['pasien']['identitas']['desaId'] ?? null" />
                <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.desaId')" class="mt-1" />
            </div>

            {{-- Kota --}}
            <div>
                <livewire:lov.kabupaten.lov-kabupaten target="kota_identitas" :propinsiId="$dataPasien['pasien']['identitas']['propinsiId'] ?? null" :initialKabId="$dataPasien['pasien']['identitas']['kotaId'] ?? null"
                    :showAsInput="true" />
                <x-input-error :messages="$errors->get('dataPasien.pasien.identitas.kotaId')" class="mt-1" />
            </div>

            {{-- Negara --}}
            <div>
                <x-input-label value="Negara" />
                <x-text-input wire:model.live="dataPasien.pasien.identitas.negara" placeholder="isi dengan ID"
                    class="w-full mt-1" />
            </div>
        </div>
    </div>
</x-border-form>
