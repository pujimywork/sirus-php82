<div>
    <div class="w-full mb-1">
        <div class="mb-2">
            <x-input-label for="waktuPemeriksaan" :value="__('Waktu Pemeriksaan')" :required="__(false)" />
            <div class="flex items-center mb-2">
                <x-text-input id="waktuPemeriksaan" placeholder="Waktu Pemeriksaan [dd/mm/yyyy hh:mi:ss]" class="mt-1 ml-2"
                    :error="$errors->has('dataDaftarPoliRJ.perencanaan.pengkajianMedis.waktuPemeriksaan')" :disabled="$isFormLocked"
                    wire:model.live="dataDaftarPoliRJ.perencanaan.pengkajianMedis.waktuPemeriksaan" />

                @if (empty($dataDaftarPoliRJ['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] ?? null) && !$isFormLocked)
                    <div class="w-1/2 ml-2">
                        <div wire:loading wire:target="setWaktuPemeriksaan">
                            <x-loading />
                        </div>
                        <x-primary-button :disabled=false
                            wire:click.prevent="setWaktuPemeriksaan('{{ now()->format('d/m/Y H:i:s') }}')"
                            type="button" wire:loading.remove>
                            Waktu Pemeriksaan
                        </x-primary-button>
                    </div>
                @endif
            </div>
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.perencanaan.pengkajianMedis.waktuPemeriksaan')" class="mt-1" />
        </div>

        <div class="mb-2">
            <x-input-label for="selesaiPemeriksaan" :value="__('Selesai Pemeriksaan')" :required="__(false)" />
            <div class="flex items-center mb-2">
                <x-text-input id="selesaiPemeriksaan" placeholder="Selesai Pemeriksaan [dd/mm/yyyy hh:mi:ss]"
                    class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.perencanaan.pengkajianMedis.selesaiPemeriksaan')" :disabled="$isFormLocked"
                    wire:model.live="dataDaftarPoliRJ.perencanaan.pengkajianMedis.selesaiPemeriksaan" />

                @if (empty($dataDaftarPoliRJ['perencanaan']['pengkajianMedis']['selesaiPemeriksaan'] ?? null) && !$isFormLocked)
                    <div class="w-1/2 ml-2">
                        <div wire:loading wire:target="setSelesaiPemeriksaan">
                            <x-loading />
                        </div>
                        <x-primary-button :disabled=false
                            wire:click.prevent="setSelesaiPemeriksaan('{{ now()->format('d/m/Y H:i:s') }}')"
                            type="button" wire:loading.remove>
                            Selesai Pemeriksaan
                        </x-primary-button>
                    </div>
                @endif
            </div>
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.perencanaan.pengkajianMedis.selesaiPemeriksaan')" class="mt-1" />
        </div>

        <div class="mb-2">
            <x-input-label for="drPemeriksa" :value="__('Dokter Pemeriksa')" :required="__(false)" />
            <div class="grid grid-cols-1 gap-2">
                <x-text-input id="drPemeriksa" placeholder="Dokter Pemeriksa" class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.perencanaan.pengkajianMedis.drPemeriksa')"
                    :disabled=true wire:model="dataDaftarPoliRJ.perencanaan.pengkajianMedis.drPemeriksa" />

                @if (!$isFormLocked)
                    <x-primary-button :disabled=false wire:click.prevent="setDrPemeriksa" type="button"
                        wire:loading.remove>
                        TTD Dokter
                    </x-primary-button>
                @endif
            </div>
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.perencanaan.pengkajianMedis.drPemeriksa')" class="mt-1" />
        </div>
    </div>
</div>
