<x-border-form :title="__('Pengkajian')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="mt-4 space-y-4">

        {{-- Perawat Penerima --}}
        <div>
            <x-input-label for="dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.perawatPenerima" value="Perawat Penerima"
                :required="true" />

            <div class="flex gap-2 mt-1">
                <x-text-input id="dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.perawatPenerima"
                    wire:model.live="dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.perawatPenerima"
                    placeholder="Perawat Penerima" class="w-full" :error="$errors->has('dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.perawatPenerima')" :disabled="true" />

                <x-outline-button type="button" class="whitespace-nowrap" wire:click.prevent="setPerawatPenerima"
                    wire:loading.attr="disabled" wire:target="setPerawatPenerima">
                    <span wire:loading.remove wire:target="setPerawatPenerima" class="inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        Ttd Perawat
                    </span>
                    <span wire:loading wire:target="setPerawatPenerima" class="inline-flex items-center gap-1.5">
                        <x-loading /> Menyimpan...
                    </span>
                </x-outline-button>
            </div>

            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.perawatPenerima')" class="mt-1" />

            {{-- Waktu datang otomatis saat TTD --}}
            <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">
                Waktu Datang:
                <span class="font-medium text-gray-600 dark:text-gray-300">
                    {{ $dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? '-' }}
                </span>
            </p>
        </div>

        {{-- Keluhan Utama --}}
        <div>
            <x-input-label for="dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama" value="Keluhan Utama"
                :required="true" />

            <x-textarea id="dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama"
                wire:model.live="dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama" placeholder="Keluhan Utama"
                :error="$errors->has('dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama')" :disabled="$isFormLocked" :rows="3" class="w-full mt-1" />

            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama')" class="mt-1" />
        </div>

        {{-- SNOMED CT — Keluhan Utama (untuk Satu Sehat) --}}
        <div>
            <livewire:lov.snomed.lov-snomed
                target="keluhanUtamaSnomed"
                label="Kode SNOMED Keluhan Utama (Satu Sehat)"
                placeholder="Ketik keluhan dalam Bahasa Indonesia / Inggris..."
                valueSet="condition-code"
                :initialSnomedCode="$dataDaftarPoliRJ['anamnesa']['keluhanUtama']['snomedCode'] ?? null"
                :disabled="$isFormLocked"
                wire:key="lov-snomed-keluhan-{{ $rjNo ?? 'new' }}-{{ $renderVersions['modal-anamnesa-rj'] ?? 0 }}"
            />
        </div>

    </div>
</x-border-form>
