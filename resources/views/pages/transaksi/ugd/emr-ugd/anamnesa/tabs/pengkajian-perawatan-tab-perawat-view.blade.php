{{-- pages/transaksi/ugd/emr-ugd/anamnesa/tabs/pengkajian-perawatan-tab.blade.php --}}
<x-border-form :title="__('Pengkajian')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="mt-4 space-y-4">

        {{-- Perawat Penerima --}}
        <div>
            <x-input-label value="Perawat Penerima" :required="true" />
            <div class="flex gap-2 mt-1">
                <x-text-input wire:model.live="dataDaftarUGD.anamnesa.pengkajianPerawatan.perawatPenerima"
                    placeholder="Perawat Penerima" class="w-full" :errorshas="$errors->has('dataDaftarUGD.anamnesa.pengkajianPerawatan.perawatPenerima')" :disabled="true" />
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
            <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.pengkajianPerawatan.perawatPenerima')" class="mt-1" />
            <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">
                Waktu Datang:
                <span class="font-medium text-gray-600 dark:text-gray-300">
                    {{ $dataDaftarUGD['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? '-' }}
                </span>
            </p>
        </div>

        {{-- Tingkat Kegawatan --}}
        <div>
            <x-input-label value="Tingkat Kegawatan (Triage)" :required="true" />
            <div class="flex flex-wrap gap-2 mt-1">
                @foreach ($dataDaftarUGD['anamnesa']['pengkajianPerawatan']['tingkatKegawatanOption'] ?? [] as $opt)
                    @php
                        $triage = $opt['tingkatKegawatan'];
                        $isActive =
                            ($dataDaftarUGD['anamnesa']['pengkajianPerawatan']['tingkatKegawatan'] ?? '') === $triage;
                        $activeClass = match ($triage) {
                            'P1' => 'border-red-500 bg-red-50 text-red-700 dark:bg-red-900/20',
                            'P2' => 'border-yellow-500 bg-yellow-50 text-yellow-700 dark:bg-yellow-900/20',
                            'P3' => 'border-green-500 bg-green-50 text-green-700 dark:bg-green-900/20',
                            'P0' => 'border-gray-700 bg-gray-100 text-gray-700 dark:bg-gray-700',
                            default => '',
                        };
                    @endphp
                    <label
                        class="flex items-center gap-1.5 px-3 py-2 text-sm font-semibold border-2 rounded-lg cursor-pointer transition-colors
                        {{ $isActive ? $activeClass : 'border-gray-200 text-gray-500 hover:border-gray-300 dark:border-gray-700' }}">
                        <input type="radio"
                            wire:model.live="dataDaftarUGD.anamnesa.pengkajianPerawatan.tingkatKegawatan"
                            value="{{ $triage }}" class="sr-only" {{ $isFormLocked ? 'disabled' : '' }} />
                        {{ $triage }}
                    </label>
                @endforeach
            </div>
            <p class="mt-1 text-xs text-gray-400">P1=Kritis • P2=Urgent • P3=Minor • P0=Meninggal</p>
            <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.pengkajianPerawatan.tingkatKegawatan')" class="mt-1" />
        </div>

        {{-- Cara Masuk IGD --}}
        <div>
            <x-input-label value="Cara Masuk IGD" :required="true" />
            <div class="flex flex-wrap gap-2 mt-1">
                @foreach ($dataDaftarUGD['anamnesa']['pengkajianPerawatan']['caraMasukIgdOption'] ?? [] as $opt)
                    <label
                        class="flex items-center gap-1.5 px-3 py-2 text-sm border rounded-lg cursor-pointer transition-colors
                        {{ ($dataDaftarUGD['anamnesa']['pengkajianPerawatan']['caraMasukIgd'] ?? '') === $opt['caraMasukIgd']
                            ? 'border-brand bg-brand/10 text-brand font-semibold dark:bg-brand/20'
                            : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300' }}">
                        <input type="radio" wire:model.live="dataDaftarUGD.anamnesa.pengkajianPerawatan.caraMasukIgd"
                            value="{{ $opt['caraMasukIgd'] }}" class="sr-only" {{ $isFormLocked ? 'disabled' : '' }} />
                        {{ $opt['caraMasukIgd'] }}
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.pengkajianPerawatan.caraMasukIgd')" class="mt-1" />
        </div>

        {{-- Sarana Transportasi --}}
        <div>
            <x-input-label value="Sarana Transportasi" />
            <div class="flex flex-wrap gap-2 mt-1">
                @foreach ($dataDaftarUGD['anamnesa']['pengkajianPerawatan']['saranaTransportasiOptions'] ?? [] as $opt)
                    <label
                        class="flex items-center gap-1.5 px-3 py-2 text-sm border rounded-lg cursor-pointer transition-colors
                        {{ ($dataDaftarUGD['anamnesa']['pengkajianPerawatan']['saranaTransportasiId'] ?? '') ===
                        $opt['saranaTransportasiId']
                            ? 'border-brand bg-brand/10 text-brand font-semibold dark:bg-brand/20'
                            : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300' }}">
                        <input type="radio"
                            wire:model.live="dataDaftarUGD.anamnesa.pengkajianPerawatan.saranaTransportasiId"
                            value="{{ $opt['saranaTransportasiId'] }}" class="sr-only"
                            {{ $isFormLocked ? 'disabled' : '' }} />
                        {{ $opt['saranaTransportasiDesc'] }}
                    </label>
                @endforeach
            </div>
            @if (($dataDaftarUGD['anamnesa']['pengkajianPerawatan']['saranaTransportasiId'] ?? '') === '4')
                <x-text-input wire:model.live="dataDaftarUGD.anamnesa.pengkajianPerawatan.saranaTransportasiKet"
                    placeholder="Sebutkan sarana transportasi..." class="w-full mt-2" :disabled="$isFormLocked" />
            @endif
        </div>

        {{-- Anamnesa Diperoleh --}}
        <div>
            <x-input-label value="Anamnesa Diperoleh Dari" />
            <div class="flex flex-wrap items-center gap-2 mt-1">
                @foreach ([['key' => 'autoanamnesa', 'label' => 'Auto-anamnesa (Pasien)'], ['key' => 'allonanamnesa', 'label' => 'Allo-anamnesa (Keluarga / Lain)']] as $item)
                    <label
                        class="flex items-center gap-1.5 px-3 py-2 text-sm border rounded-lg cursor-pointer transition-colors
                        {{ !empty($dataDaftarUGD['anamnesa']['anamnesaDiperoleh'][$item['key']] ?? [])
                            ? 'border-brand bg-brand/10 text-brand font-semibold dark:bg-brand/20'
                            : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300' }}">
                        <input type="checkbox"
                            wire:model.live="dataDaftarUGD.anamnesa.anamnesaDiperoleh.{{ $item['key'] }}"
                            value="1" class="w-4 h-4 rounded text-brand" {{ $isFormLocked ? 'disabled' : '' }} />
                        {{ $item['label'] }}
                    </label>
                @endforeach
                <x-text-input wire:model.live="dataDaftarUGD.anamnesa.anamnesaDiperoleh.anamnesaDiperolehDari"
                    placeholder="Nama pemberi keterangan (jika allo-anamnesa)" class="flex-1 min-w-[200px]"
                    :disabled="$isFormLocked" />
            </div>
        </div>

        {{-- Keluhan Utama --}}
        <div>
            <x-input-label value="Keluhan Utama" :required="true" />
            <x-textarea wire:model.live="dataDaftarUGD.anamnesa.keluhanUtama.keluhanUtama" placeholder="Keluhan Utama"
                :errorshas="$errors->has('dataDaftarUGD.anamnesa.keluhanUtama.keluhanUtama')" :disabled="$isFormLocked" :rows="3" class="w-full mt-1" />
            <x-input-error :messages="$errors->get('dataDaftarUGD.anamnesa.keluhanUtama.keluhanUtama')" class="mt-1" />
        </div>

    </div>
</x-border-form>
