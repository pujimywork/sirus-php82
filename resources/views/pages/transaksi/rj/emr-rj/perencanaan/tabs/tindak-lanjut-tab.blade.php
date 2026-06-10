<x-border-form :title="__('Tindak Lanjut')" :align="__('start')" :bgcolor="__('bg-surface-soft')">
    <div class="space-y-4">

        {{-- Select Tindak Lanjut --}}
        <div>
            <x-select-input id="tindakLanjut" wire:model.live="dataDaftarPoliRJ.perencanaan.tindakLanjut.tindakLanjut"
                :disabled="$isFormLocked" :error="$errors->has('dataDaftarPoliRJ.perencanaan.tindakLanjut.tindakLanjut')">
                <option value="">Pilih Tindak Lanjut</option>
                @foreach ($dataDaftarPoliRJ['perencanaan']['tindakLanjut']['tindakLanjutOptions'] ?? [] as $option)
                    <option value="{{ $option['tindakLanjut'] }}">
                        {{ __($option['tindakLanjut']) }}
                    </option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.perencanaan.tindakLanjut.tindakLanjut')" class="mt-1" />
        </div>

        {{-- Keterangan --}}
        <div>
            <x-text-input id="keteranganTindakLanjut" placeholder="Keterangan Tindak Lanjut" :error="$errors->has('dataDaftarPoliRJ.perencanaan.tindakLanjut.keteranganTindakLanjut')"
                :disabled="$isFormLocked" wire:model.live="dataDaftarPoliRJ.perencanaan.tindakLanjut.keteranganTindakLanjut" />
            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.perencanaan.tindakLanjut.keteranganTindakLanjut')" class="mt-1" />
        </div>

        {{-- Status PRB toggle dipindah ke modal E-Resep --}}

        {{-- SKDP — tampil hanya jika Tindak Lanjut = Kontrol --}}
        @if (($dataDaftarPoliRJ['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '') === 'Kontrol')
            <div class="pt-2 border-t border-hairline-soft dark:border-gray-700">
                <livewire:pages::transaksi.rj.emr-rj.skdp.rm-skdp-rj-actions :rjNo="$rjNo"
                    wire:key="rm-skdp-rj-{{ $rjNo }}" />
            </div>
        @endif

        {{-- PRB — tampil hanya jika Tindak Lanjut = PRB --}}
        {{-- @if (($dataDaftarPoliRJ['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '') === 'PRB')
            <div class="pt-2 border-t border-hairline-soft dark:border-gray-700">
                <livewire:pages::transaksi.rj.emr-rj.prb.rm-prb-rj-actions :rjNo="$rjNo"
                    wire:key="rm-prb-rj-{{ $rjNo }}" />
            </div>
        @endif --}}

    </div>
</x-border-form>
