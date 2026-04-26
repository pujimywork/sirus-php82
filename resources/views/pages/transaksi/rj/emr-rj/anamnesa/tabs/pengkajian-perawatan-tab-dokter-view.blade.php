<x-border-form :title="__('Pengkajian')" :align="__('start')" :bgcolor="__('bg-gray-50')">
    <div class="mt-4 divide-y divide-gray-100 dark:divide-gray-700">

        {{-- Perawat Penerima --}}
        <div class="py-3 grid grid-cols-3 gap-2 items-start">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Perawat Penerima</span>
            <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                {{ $dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['perawatPenerima'] ?? '-' }}
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.perawatPenerima')" class="mt-1" />
            </span>
        </div>

        {{-- Waktu Datang --}}
        <div class="py-3 grid grid-cols-3 gap-2 items-start">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Waktu Datang</span>
            <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                {{ $dataDaftarPoliRJ['anamnesa']['pengkajianPerawatan']['jamDatang'] ?? '-' }}
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.jamDatang')" class="mt-1" />
            </span>
        </div>

        {{-- Keluhan Utama --}}
        <div class="py-3 grid grid-cols-3 gap-2 items-start">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Keluhan Utama</span>
            <span class="col-span-2 text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line">
                {{ $dataDaftarPoliRJ['anamnesa']['keluhanUtama']['keluhanUtama'] ?? '-' }}
                <x-input-error :messages="$errors->get('dataDaftarPoliRJ.anamnesa.keluhanUtama.keluhanUtama')" class="mt-1" />
            </span>
        </div>

        {{-- SNOMED CT (readonly) --}}
        @if (!empty($dataDaftarPoliRJ['anamnesa']['keluhanUtama']['snomedCode']))
            <div class="py-3 grid grid-cols-3 gap-2 items-start">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">SNOMED CT</span>
                <span class="col-span-2 text-sm text-gray-800 dark:text-gray-200">
                    @php
                        $sId = $dataDaftarPoliRJ['anamnesa']['keluhanUtama']['snomedDisplayId'] ?? '';
                        $sEn = $dataDaftarPoliRJ['anamnesa']['keluhanUtama']['snomedDisplayEn'] ?? '';
                        $sCode = $dataDaftarPoliRJ['anamnesa']['keluhanUtama']['snomedCode'];
                    @endphp
                    @if (!empty($sId))
                        {{ $sId }} &mdash; {{ $sEn }} ({{ $sCode }})
                    @else
                        {{ $sEn }} ({{ $sCode }})
                    @endif
                </span>
            </div>
        @endif

    </div>
</x-border-form>
