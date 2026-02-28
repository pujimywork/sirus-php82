<div class="w-full mb-1">

    {{-- Field Waktu Datang --}}
    <div class="mb-2">
        <x-input-label for="dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.jamDatang" :value="__('Waktu Datang')"
            :required="__(true)" />

        <div class="grid grid-cols-1 gap-2 mb-2 ml-2">
            <x-text-input id="dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.jamDatang"
                placeholder="Waktu Datang [dd/mm/yyyy hh24:mi:ss]" class="mt-1" :errorshas="__($errors->has('dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.jamDatang'))" :disabled="$isFormLocked"
                wire:model.live="dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.jamDatang" />

            @if (!$dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['jamDatang'])
                <div class="grid grid-cols-1">
                    <div wire:loading wire:target="setJamDatang">
                        <x-loading />
                    </div>
                    <x-primary-button :disabled="false"
                        wire:click.prevent="setJamDatang('{{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }}')"
                        type="button" wire:loading.remove>
                        Set Jam Datang
                    </x-primary-button>
                </div>
            @endif
        </div>

        {{-- Error untuk Waktu Datang --}}
        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.jamDatang')" class="mt-1 ml-2" />
    </div>

    {{-- Field Perawat Penerima --}}
    <div class="mb-2">
        <x-input-label for="dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.perawatPenerima" :value="__('Perawat Penerima')"
            :required="__(true)" class="pt-2" />

        <div class="grid grid-cols-1 gap-2 ml-2">
            <x-text-input id="dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.perawatPenerima"
                name="dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.perawatPenerima" placeholder="Perawat Penerima"
                class="mt-1 " :errorshas="__($errors->has('dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.perawatPenerima'))" :disabled="true"
                wire:model.live="dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.perawatPenerima" />

            <div class="grid grid-cols-1">
                <div wire:loading wire:target="setPerawatPenerima">
                    <x-loading />
                </div>
                <x-primary-button :disabled="false" wire:click.prevent="setPerawatPenerima()" type="button"
                    wire:loading.remove>
                    ttd Perawat
                </x-primary-button>
            </div>
        </div>

        {{-- Error untuk Perawat Penerima --}}
        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.perawatPenerima')" class="mt-1 ml-2" />
    </div>
    {{-- Include tab-tab anamnesa --}}
    @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.keluhan-utama-tab')
    @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.riwayat-penyakit-sekarang-tab')
    @include('pages.transaksi.rj.emr-rj.anamnesa.tabs.riwayat-penyakit-dahulu-tab')
</div>
