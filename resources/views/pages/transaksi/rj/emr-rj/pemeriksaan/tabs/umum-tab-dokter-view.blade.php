<div class="space-y-4">

    {{-- TANDA VITAL --}}
    <x-border-form :title="__('Tanda Vital')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 grid grid-cols-2 gap-x-3">

            <div class="flex items-center justify-between gap-2 py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Keadaan Umum</span>
                <span class="text-base font-medium text-right text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarPoliRJ['pemeriksaan']['tandaVital']['keadaanUmum'] ?? '-' }}
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.keadaanUmum')" class="mt-1" />
                </span>
            </div>

            <div class="flex items-center justify-between gap-2 py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Tingkat Kesadaran</span>
                <span class="text-base font-medium text-right text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarPoliRJ['pemeriksaan']['tandaVital']['tingkatKesadaran'] ?? '-' }}
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.tingkatKesadaran')" class="mt-1" />
                </span>
            </div>

            <div class="flex items-center justify-between gap-2 py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Tekanan Darah</span>
                <span class="text-base font-medium text-right text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarPoliRJ['pemeriksaan']['tandaVital']['sistolik'] ?? '-' }}
                    /
                    {{ $dataDaftarPoliRJ['pemeriksaan']['tandaVital']['distolik'] ?? '-' }}
                    <span class="text-sm text-gray-400">mmHg</span>
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.sistolik')" class="mt-1" />
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.distolik')" class="mt-1" />
                </span>
            </div>

            <div class="flex items-center justify-between gap-2 py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Frekuensi Nadi</span>
                <span class="text-base font-medium text-right text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarPoliRJ['pemeriksaan']['tandaVital']['frekuensiNadi'] ?? '-' }}
                    <span class="text-sm text-gray-400">x/menit</span>
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNadi')" class="mt-1" />
                </span>
            </div>

            <div class="flex items-center justify-between gap-2 py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Frekuensi Nafas</span>
                <span class="text-base font-medium text-right text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarPoliRJ['pemeriksaan']['tandaVital']['frekuensiNafas'] ?? '-' }}
                    <span class="text-sm text-gray-400">x/menit</span>
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNafas')" class="mt-1" />
                </span>
            </div>

            <div class="flex items-center justify-between gap-2 py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Suhu</span>
                <span class="text-base font-medium text-right text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarPoliRJ['pemeriksaan']['tandaVital']['suhu'] ?? '-' }}
                    <span class="text-sm text-gray-400">°C</span>
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.suhu')" class="mt-1" />
                </span>
            </div>

            <div class="flex items-center justify-between gap-2 py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">SPO2</span>
                <span class="text-base font-medium text-right text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarPoliRJ['pemeriksaan']['tandaVital']['spo2'] ?? '-' }}
                    <span class="text-sm text-gray-400">%</span>
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.spo2')" class="mt-1" />
                </span>
            </div>

            <div class="flex items-center justify-between gap-2 py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">GDA</span>
                <span class="text-base font-medium text-right text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarPoliRJ['pemeriksaan']['tandaVital']['gda'] ?? '-' }}
                    <span class="text-sm text-gray-400">g/dl</span>
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.tandaVital.gda')" class="mt-1" />
                </span>
            </div>

        </div>
    </x-border-form>

    {{-- NUTRISI --}}
    <x-border-form :title="__('Nutrisi')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 grid grid-cols-2 gap-x-3">

            <div class="flex items-center justify-between gap-2 py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Berat Badan</span>
                <span class="text-base font-medium text-right text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarPoliRJ['pemeriksaan']['nutrisi']['bb'] ?? '-' }}
                    <span class="text-sm text-gray-400">Kg</span>
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.nutrisi.bb')" class="mt-1" />
                </span>
            </div>

            <div class="flex items-center justify-between gap-2 py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Tinggi Badan</span>
                <span class="text-base font-medium text-right text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarPoliRJ['pemeriksaan']['nutrisi']['tb'] ?? '-' }}
                    <span class="text-sm text-gray-400">Cm</span>
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.nutrisi.tb')" class="mt-1" />
                </span>
            </div>

            <div class="flex items-center justify-between gap-2 py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Index Masa Tubuh</span>
                <span class="text-base font-medium text-right text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarPoliRJ['pemeriksaan']['nutrisi']['imt'] ?? '-' }}
                    <span class="text-sm text-gray-400">Kg/M²</span>
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.nutrisi.imt')" class="mt-1" />
                </span>
            </div>

            <div class="flex items-center justify-between gap-2 py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Lingkar Kepala</span>
                <span class="text-base font-medium text-right text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarPoliRJ['pemeriksaan']['nutrisi']['lk'] ?? '-' }}
                    <span class="text-sm text-gray-400">Cm</span>
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.nutrisi.lk')" class="mt-1" />
                </span>
            </div>

            <div class="flex items-center justify-between gap-2 py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Lingkar Lengan Atas</span>
                <span class="text-base font-medium text-right text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarPoliRJ['pemeriksaan']['nutrisi']['lila'] ?? '-' }}
                    <span class="text-sm text-gray-400">Cm</span>
                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.pemeriksaan.nutrisi.lila')" class="mt-1" />
                </span>
            </div>

        </div>
    </x-border-form>

</div>
