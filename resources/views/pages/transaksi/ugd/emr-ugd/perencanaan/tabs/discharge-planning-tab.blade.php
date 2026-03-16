{{-- pages/transaksi/ugd/emr-ugd/perencanaan/tabs/discharge-planning-tab.blade.php --}}
<div class="space-y-4">

    {{-- PELAYANAN BERKELANJUTAN --}}
    <x-border-form :title="__('Pelayanan Berkelanjutan')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 space-y-4">

            <div>
                <x-input-label value="Status" />
                <div class="mt-1">
                    <x-select-input
                        wire:model.live="dataDaftarUGD.perencanaan.dischargePlanning.pelayananBerkelanjutan.pelayananBerkelanjutan"
                        :disabled="$isFormLocked" :error="$errors->has(
                            'dataDaftarUGD.perencanaan.dischargePlanning.pelayananBerkelanjutan.pelayananBerkelanjutan',
                        )">
                        <option value="">Pilih Status</option>
                        @foreach ($dataDaftarUGD['perencanaan']['dischargePlanning']['pelayananBerkelanjutan']['pelayananBerkelanjutanOption'] ?? [] as $option)
                            <option value="{{ $option['pelayananBerkelanjutan'] }}">
                                {{ __($option['pelayananBerkelanjutan']) }}
                            </option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get(
                        'dataDaftarUGD.perencanaan.dischargePlanning.pelayananBerkelanjutan.pelayananBerkelanjutan',
                    )" class="mt-1" />
                </div>
            </div>

            @if (
                ($dataDaftarUGD['perencanaan']['dischargePlanning']['pelayananBerkelanjutan']['pelayananBerkelanjutan'] ?? '') ===
                    'Ada')
                <div
                    class="grid grid-cols-2 gap-3 p-3 border-l-2 border-brand-green/40 bg-brand-green/5 rounded-r-lg dark:bg-brand-green/10">
                    @foreach ([
        'rawatLuka' => 'Rawat Luka',
        'dm' => 'DM',
        'ppok' => 'PPOK',
        'hivAids' => 'HIV/AIDS',
        'dmTerapiInsulin' => 'DM Terapi Insulin',
        'ckd' => 'CKD',
        'tb' => 'TB',
        'stroke' => 'Stroke',
        'kemoterapi' => 'Kemoterapi',
    ] as $field => $label)
                        <div class="flex items-center space-x-2">
                            <x-toggle
                                wire:model.live="dataDaftarUGD.perencanaan.dischargePlanning.pelayananBerkelanjutanOpsi.{{ $field }}"
                                trueValue="1" falseValue="0" :disabled="$isFormLocked">
                                {{ $label }}
                            </x-toggle>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </x-border-form>

    {{-- PENGGUNAAN ALAT BANTU --}}
    <x-border-form :title="__('Penggunaan Alat Bantu')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 space-y-4">

            <div>
                <x-input-label value="Status" />
                <div class="mt-1">
                    <x-select-input
                        wire:model.live="dataDaftarUGD.perencanaan.dischargePlanning.penggunaanAlatBantu.penggunaanAlatBantu"
                        :disabled="$isFormLocked" :error="$errors->has(
                            'dataDaftarUGD.perencanaan.dischargePlanning.penggunaanAlatBantu.penggunaanAlatBantu',
                        )">
                        <option value="">Pilih Status</option>
                        @foreach ($dataDaftarUGD['perencanaan']['dischargePlanning']['penggunaanAlatBantu']['penggunaanAlatBantuOption'] ?? [] as $option)
                            <option value="{{ $option['penggunaanAlatBantu'] }}">
                                {{ __($option['penggunaanAlatBantu']) }}
                            </option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get(
                        'dataDaftarUGD.perencanaan.dischargePlanning.penggunaanAlatBantu.penggunaanAlatBantu',
                    )" class="mt-1" />
                </div>
            </div>

            @if (($dataDaftarUGD['perencanaan']['dischargePlanning']['penggunaanAlatBantu']['penggunaanAlatBantu'] ?? '') === 'Ada')
                <div
                    class="grid grid-cols-2 gap-3 p-3 border-l-2 border-brand-green/40 bg-brand-green/5 rounded-r-lg dark:bg-brand-green/10">
                    @foreach ([
        'kateterUrin' => 'Kateter Urin',
        'ngt' => 'NGT',
        'traechotomy' => 'Tracheotomy',
        'colostomy' => 'Colostomy',
    ] as $field => $label)
                        <div class="flex items-center space-x-2">
                            <x-toggle
                                wire:model.live="dataDaftarUGD.perencanaan.dischargePlanning.penggunaanAlatBantuOpsi.{{ $field }}"
                                trueValue="1" falseValue="0" :disabled="$isFormLocked">
                                {{ $label }}
                            </x-toggle>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </x-border-form>

</div>
