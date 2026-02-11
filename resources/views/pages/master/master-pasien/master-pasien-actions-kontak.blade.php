<x-border-form :title="__('Kontak')" :align="__('start')" :bgcolor="__('bg-white')">
    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {{-- No HP Pasien --}}
            <div>
                <x-input-label value="No HP Pasien" :required="true" />
                <div class="flex gap-2 mt-1">
                    <x-text-input wire:model.live="dataPasien.pasien.kontak.kodenegara" placeholder="Kode"
                        class="w-20" />
                    <x-text-input wire:model.live="dataPasien.pasien.kontak.nomerTelponSelulerPasien" :error="$errors->has('dataPasien.pasien.kontak.nomerTelponSelulerPasien')"
                        class="flex-1" />
                </div>
                <x-input-error :messages="$errors->get('dataPasien.pasien.kontak.nomerTelponSelulerPasien')" class="mt-1" />
            </div>

            {{-- No HP Lain --}}
            <div>
                <x-input-label value="No HP Lain" />
                <div class="flex gap-2 mt-1">
                    <x-text-input wire:model.live="dataPasien.pasien.kontak.kodenegara" placeholder="Kode"
                        class="w-20" />
                    <x-text-input wire:model.live="dataPasien.pasien.kontak.nomerTelponLain" class="flex-1" />
                </div>
            </div>
        </div>
    </div>
</x-border-form>
