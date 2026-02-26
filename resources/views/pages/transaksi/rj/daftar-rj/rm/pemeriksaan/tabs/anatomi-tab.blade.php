<div class="w-full mb-1">
    <div class="pt-0">
        <x-input-label for="dataDaftarPoliRJ.pemeriksaan.anatomi" :value="__('Anatomi')" :required="__(false)"
            class="pt-2 sm:text-xl" />

        <div id="TransaksiRawatJalan" x-data="{ activeTabAnatomi: 'kepala' }" class="flex">
            <div class="w-[200px] h-80 overflow-auto">
                @foreach ($dataDaftarPoliRJ['pemeriksaan']['anatomi'] as $key => $pAnatomi)
                    <div class="flex px-2 mb-2 border-b border-gray-200 dark:border-gray-700">
                        <ul class="inline -mb-px text-xs font-medium text-center text-gray-500">
                            <li class="mr-2">
                                <label
                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                    :class="activeTabAnatomi === '{{ $key }}'
                                        ?
                                        'text-primary border-primary bg-gray-100' :
                                        ''"
                                    @click="activeTabAnatomi ='{{ $key }}'">{{ strtoupper($key) }}</label>
                            </li>
                        </ul>
                    </div>
                @endforeach
            </div>

            <div class="w-full">
                @foreach ($dataDaftarPoliRJ['pemeriksaan']['anatomi'] as $key => $pAnatomi)
                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800"
                        :class="{
                            'active': activeTabAnatomi === '{{ $key }}'
                        }"
                        x-show.transition.in.opacity.duration.600="activeTabAnatomi === '{{ $key }}'">

                        <x-input-label for="dataDaftarPoliRJ.pemeriksaan.anatomi.{{ $key }}.kelainan"
                            :value="__(strtoupper($key))" :required="__(false)" />

                        <div class="mt-2 ml-2">
                            <x-select-input id="kelainan-{{ $key }}"
                                wire:model.live="dataDaftarPoliRJ.pemeriksaan.anatomi.{{ $key }}.kelainan">
                                @foreach ($pAnatomi['kelainanOptions'] as $kelainanOptions)
                                    <option value="{{ $kelainanOptions['kelainan'] }}">
                                        {{ __($kelainanOptions['kelainan']) }}
                                    </option>
                                @endforeach
                            </x-select-input>
                        </div>

                        {{-- Error untuk radio button kelainan --}}
                        <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.anatomi.' . $key . '.kelainan')" class="mt-1" />

                        <div class="mt-2">
                            <x-textarea id="dataDaftarPoliRJ.pemeriksaan.anatomi.{{ $key }}.desc"
                                placeholder="{{ strtoupper($key) }}" class="mt-1 ml-2" :error="$errors->has('dataDaftarPoliRJ.pemeriksaan.anatomi.' . $key . '.desc')"
                                :disabled="$isFormLocked"
                                wire:model.live="dataDaftarPoliRJ.pemeriksaan.anatomi.{{ $key }}.desc"
                                rows="3" />
                            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.anatomi.' . $key . '.desc')" class="mt-1" />
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
