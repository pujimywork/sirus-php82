<div>
    <div class="w-full mb-4">
        <h3 class="mb-2 text-lg font-medium">Pelayanan Berkelanjutan</h3>

        <div class="mb-3">
            <x-input-label for="pelayananBerkelanjutan" :value="__('Status')" />
            <div class="mt-1">
                <x-select-input id="pelayananBerkelanjutan"
                    wire:model.live="dataDaftarPoliRJ.perencanaan.dischargePlanning.pelayananBerkelanjutan.pelayananBerkelanjutan"
                    :disabled="$isFormLocked" :error="$errors->has(
                        'dataDaftarPoliRJ.perencanaan.dischargePlanning.pelayananBerkelanjutan.pelayananBerkelanjutan',
                    )">
                    <option value="">Pilih Status</option>
                    @foreach ($dataDaftarPoliRJ['perencanaan']['dischargePlanning']['pelayananBerkelanjutan']['pelayananBerkelanjutanOption'] ?? [] as $option)
                        <option value="{{ $option['pelayananBerkelanjutan'] }}">
                            {{ __($option['pelayananBerkelanjutan']) }}
                        </option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get(
                    'dataDaftarPoliRJ.perencanaan.dischargePlanning.pelayananBerkelanjutan.pelayananBerkelanjutan',
                )" class="mt-1" />
            </div>
        </div>

        @if (
            ($dataDaftarPoliRJ['perencanaan']['dischargePlanning']['pelayananBerkelanjutan']['pelayananBerkelanjutan'] ??
                '') ===
                'Ada')
            <div class="grid grid-cols-2 gap-3 p-3 ml-4 border-l-2 border-gray-200">
                @foreach (['rawatLuka' => 'Rawat Luka', 'dm' => 'DM', 'ppok' => 'PPOK', 'hivAids' => 'HIV/AIDS', 'dmTerapiInsulin' => 'DM Terapi Insulin', 'ckd' => 'CKD', 'tb' => 'TB', 'stroke' => 'Stroke', 'kemoterapi' => 'Kemoterapi'] as $field => $label)
                    <div class="flex items-center space-x-2">
                        <x-toggle
                            wire:model.live="dataDaftarPoliRJ.perencanaan.dischargePlanning.pelayananBerkelanjutanOpsi.{{ $field }}"
                            trueValue="1" falseValue="0" :disabled="$isFormLocked">
                            {{ $label }}
                        </x-toggle>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="w-full mb-4">
        <h3 class="mb-2 text-lg font-medium">Penggunaan Alat Bantu</h3>

        <div class="mb-3">
            <x-input-label for="penggunaanAlatBantu" :value="__('Status')" />
            <div class="mt-1">
                <x-select-input id="penggunaanAlatBantu"
                    wire:model.live="dataDaftarPoliRJ.perencanaan.dischargePlanning.penggunaanAlatBantu.penggunaanAlatBantu"
                    :disabled="$isFormLocked" :error="$errors->has(
                        'dataDaftarPoliRJ.perencanaan.dischargePlanning.penggunaanAlatBantu.penggunaanAlatBantu',
                    )">
                    <option value="">Pilih Status</option>
                    @foreach ($dataDaftarPoliRJ['perencanaan']['dischargePlanning']['penggunaanAlatBantu']['penggunaanAlatBantuOption'] ?? [] as $option)
                        <option value="{{ $option['penggunaanAlatBantu'] }}">
                            {{ __($option['penggunaanAlatBantu']) }}
                        </option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get(
                    'dataDaftarPoliRJ.perencanaan.dischargePlanning.penggunaanAlatBantu.penggunaanAlatBantu',
                )" class="mt-1" />
            </div>
        </div>

        @if (
            ($dataDaftarPoliRJ['perencanaan']['dischargePlanning']['penggunaanAlatBantu']['penggunaanAlatBantu'] ?? '') ===
                'Ada')
            <div class="grid grid-cols-2 gap-3 p-3 ml-4 border-l-2 border-gray-200">
                @foreach (['kateterUrin' => 'Kateter Urin', 'ngt' => 'NGT', 'traechotomy' => 'Tracheotomy', 'colostomy' => 'Colostomy'] as $field => $label)
                    <div class="flex items-center space-x-2">
                        <x-toggle
                            wire:model.live="dataDaftarPoliRJ.perencanaan.dischargePlanning.penggunaanAlatBantuOpsi.{{ $field }}"
                            trueValue="1" falseValue="0" :disabled="$isFormLocked">
                            {{ $label }}
                        </x-toggle>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
