{{-- pages/transaksi/ugd/emr-ugd/pemeriksaan/tabs/anatomi-tab.blade.php --}}
@php
    $anatomiData = $dataDaftarUGD['pemeriksaan']['anatomi'] ?? [];

    if (empty($anatomiData)) {
        $anatomiData = collect(['kepala', 'mata', 'telinga', 'hidung', 'rambut', 'bibir', 'gigiGeligi', 'lidah', 'langitLangit', 'leher', 'tenggorokan', 'tonsil', 'dada', 'payudarah', 'punggung', 'perut', 'genital', 'anus', 'lenganAtas', 'lenganBawah', 'jariTangan', 'kukuTangan', 'persendianTangan', 'tungkaiAtas', 'tungkaiBawah', 'jariKaki', 'kukuKaki', 'persendianKaki', 'faring'])
            ->mapWithKeys(fn($part) => [$part => ['kelainan' => 'Tidak Diperiksa', 'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']], 'desc' => '']])
            ->toArray();
    }
@endphp
<x-border-form :title="__('Anatomi')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="mt-4" x-data="{ activeTabAnatomi: '{{ !empty($anatomiData) ? array_key_first($anatomiData) : '' }}' }">
        <div class="flex gap-4">

            {{-- SIDEBAR TABS --}}
            <div
                class="w-44 shrink-0 overflow-y-auto max-h-80 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                @foreach ($anatomiData as $key => $pAnatomi)
                    <button type="button" @click="activeTabAnatomi = '{{ $key }}'"
                        class="w-full text-left px-3 py-2.5 text-xs font-medium border-b border-gray-100 dark:border-gray-700 transition-colors last:border-0"
                        :class="activeTabAnatomi === '{{ $key }}'
                            ?
                            'bg-brand text-white' :
                            'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800'">
                        {{ strtoupper($key) }}
                    </button>
                @endforeach
            </div>

            {{-- PANEL KONTEN --}}
            <div class="flex-1 min-w-0">
                @foreach ($anatomiData as $key => $pAnatomi)
                    <div x-show="activeTabAnatomi === '{{ $key }}'"
                        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100" class="space-y-3">

                        {{-- Kelainan --}}
                        <div>
                            <x-input-label :value="__(strtoupper($key) . ' — Kelainan')" />
                            <x-select-input
                                wire:model.live="dataDaftarUGD.pemeriksaan.anatomi.{{ $key }}.kelainan"
                                :disabled="$isFormLocked" class="w-full mt-1">
                                @foreach ($pAnatomi['kelainanOptions'] as $kelainanOptions)
                                    <option value="{{ $kelainanOptions['kelainan'] }}">
                                        {{ $kelainanOptions['kelainan'] }}
                                    </option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.anatomi.' . $key . '.kelainan')" class="mt-1" />
                        </div>

                        {{-- Deskripsi --}}
                        <div>
                            <x-input-label value="Deskripsi" />
                            <x-textarea wire:model.live="dataDaftarUGD.pemeriksaan.anatomi.{{ $key }}.desc"
                                placeholder="{{ strtoupper($key) }}" :error="$errors->has('dataDaftarUGD.pemeriksaan.anatomi.' . $key . '.desc')" :disabled="$isFormLocked" rows="4"
                                class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('dataDaftarUGD.pemeriksaan.anatomi.' . $key . '.desc')" class="mt-1" />
                        </div>

                    </div>
                @endforeach
            </div>

        </div>
    </div>
</x-border-form>
