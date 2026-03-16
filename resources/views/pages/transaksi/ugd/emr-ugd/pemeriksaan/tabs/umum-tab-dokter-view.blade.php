{{-- pages/transaksi/ugd/emr-ugd/pemeriksaan/tabs/umum-tab-dokter-view.blade.php --}}
<div class="space-y-4">

    {{-- TANDA VITAL --}}
    <x-border-form :title="__('Tanda Vital')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 divide-y divide-gray-100 dark:divide-gray-700">

            <div class="py-3 grid grid-cols-3 gap-2 items-center">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Keadaan Umum</span>
                <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarUGD['pemeriksaan']['tandaVital']['keadaanUmum'] ?? '-' }}
                </span>
            </div>

            <div class="py-3 grid grid-cols-3 gap-2 items-center">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Tingkat Kesadaran</span>
                <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarUGD['pemeriksaan']['tandaVital']['tingkatKesadaran'] ?? '-' }}
                </span>
            </div>

            <div class="py-3 grid grid-cols-3 gap-2 items-center">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Tekanan Darah</span>
                <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarUGD['pemeriksaan']['tandaVital']['sistolik'] ?? '-' }}
                    /
                    {{ $dataDaftarUGD['pemeriksaan']['tandaVital']['distolik'] ?? '-' }}
                    <span class="text-xs text-gray-400">mmHg</span>
                </span>
            </div>

            <div class="py-3 grid grid-cols-3 gap-2 items-center">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Frekuensi Nadi</span>
                <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarUGD['pemeriksaan']['tandaVital']['frekuensiNadi'] ?? '-' }}
                    <span class="text-xs text-gray-400">x/menit</span>
                </span>
            </div>

            <div class="py-3 grid grid-cols-3 gap-2 items-center">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Frekuensi Nafas</span>
                <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarUGD['pemeriksaan']['tandaVital']['frekuensiNafas'] ?? '-' }}
                    <span class="text-xs text-gray-400">x/menit</span>
                </span>
            </div>

            <div class="py-3 grid grid-cols-3 gap-2 items-center">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Suhu</span>
                <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarUGD['pemeriksaan']['tandaVital']['suhu'] ?? '-' }}
                    <span class="text-xs text-gray-400">°C</span>
                </span>
            </div>

            <div class="py-3 grid grid-cols-3 gap-2 items-center">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">SPO2</span>
                <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarUGD['pemeriksaan']['tandaVital']['spo2'] ?? '-' }}
                    <span class="text-xs text-gray-400">%</span>
                </span>
            </div>

            <div class="py-3 grid grid-cols-3 gap-2 items-center">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">GDA</span>
                <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarUGD['pemeriksaan']['tandaVital']['gda'] ?? '-' }}
                    <span class="text-xs text-gray-400">g/dl</span>
                </span>
            </div>

        </div>
    </x-border-form>

    {{-- NUTRISI --}}
    <x-border-form :title="__('Nutrisi')" :align="__('start')" :bgcolor="__('bg-gray-50')">
        <div class="mt-4 divide-y divide-gray-100 dark:divide-gray-700">

            <div class="py-3 grid grid-cols-3 gap-2 items-center">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Berat Badan</span>
                <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarUGD['pemeriksaan']['nutrisi']['bb'] ?? '-' }}
                    <span class="text-xs text-gray-400">Kg</span>
                </span>
            </div>

            <div class="py-3 grid grid-cols-3 gap-2 items-center">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Tinggi Badan</span>
                <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarUGD['pemeriksaan']['nutrisi']['tb'] ?? '-' }}
                    <span class="text-xs text-gray-400">Cm</span>
                </span>
            </div>

            <div class="py-3 grid grid-cols-3 gap-2 items-center">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Index Masa Tubuh</span>
                <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarUGD['pemeriksaan']['nutrisi']['imt'] ?? '-' }}
                    <span class="text-xs text-gray-400">Kg/M²</span>
                </span>
            </div>

            <div class="py-3 grid grid-cols-3 gap-2 items-center">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Lingkar Kepala</span>
                <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarUGD['pemeriksaan']['nutrisi']['lk'] ?? '-' }}
                    <span class="text-xs text-gray-400">Cm</span>
                </span>
            </div>

            <div class="py-3 grid grid-cols-3 gap-2 items-center">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Lingkar Lengan Atas</span>
                <span class="col-span-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                    {{ $dataDaftarUGD['pemeriksaan']['nutrisi']['lila'] ?? '-' }}
                    <span class="text-xs text-gray-400">Cm</span>
                </span>
            </div>

        </div>
    </x-border-form>

</div>
