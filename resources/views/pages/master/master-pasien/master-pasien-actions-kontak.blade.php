<x-border-form :title="__('Kontak')" :align="__('start')" :bgcolor="__('bg-canvas')">
    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {{-- No HP Pasien --}}
            <div>
                <x-input-label value="No HP Pasien" :required="true" />
                <x-text-input wire:model.live="dataPasien.pasien.kontak.nomerTelponSelulerPasien" :error="$errors->has('dataPasien.pasien.kontak.nomerTelponSelulerPasien')"
                    class="w-full mt-1" placeholder="cth: 081234567890" />
                <x-input-error :messages="$errors->get('dataPasien.pasien.kontak.nomerTelponSelulerPasien')" class="mt-1" />
            </div>

            {{-- No HP Lain --}}
            <div>
                <x-input-label value="No HP Lain" />
                <x-text-input wire:model.live="dataPasien.pasien.kontak.nomerTelponLain"
                    class="w-full mt-1" placeholder="Opsional" />
            </div>
        </div>
    </div>
</x-border-form>
