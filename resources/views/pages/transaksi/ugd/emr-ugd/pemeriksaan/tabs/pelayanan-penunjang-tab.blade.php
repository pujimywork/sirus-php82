{{-- pages/transaksi/ugd/emr-ugd/pemeriksaan/tabs/pelayanan-penunjang-tab.blade.php --}}
<div class="w-full mb-1 space-y-4">

    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Laboratorium</h3>
        <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.laborat.rm-laborat-ugd-actions
            :rjNo="$dataDaftarUGD['rjNo'] ?? ''" :disabled="$isFormLocked"
            wire:key="laborat-ugd-actions-{{ $dataDaftarUGD['rjNo'] ?? 'new' }}" />

        <div class="mt-3">
            <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.laborat.rm-daftar-laborat-ugd
                :rjNo="$dataDaftarUGD['rjNo'] ?? ''"
                wire:key="daftar-laborat-ugd-{{ $dataDaftarUGD['rjNo'] ?? 'new' }}" />
        </div>
    </div>

    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Laboratorium Luar</h3>
        <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.laborat.rm-laborat-luar-ugd-actions
            :rjNo="$dataDaftarUGD['rjNo'] ?? ''" :disabled="$isFormLocked"
            wire:key="laborat-luar-ugd-actions-{{ $dataDaftarUGD['rjNo'] ?? 'new' }}" />

        <div class="mt-3">
            <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.laborat.rm-daftar-laborat-luar-ugd
                :rjNo="$dataDaftarUGD['rjNo'] ?? ''"
                wire:key="daftar-laborat-luar-ugd-{{ $dataDaftarUGD['rjNo'] ?? 'new' }}" />
        </div>
    </div>

    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Radiologi</h3>
        <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.radiologi.rm-radiologi-ugd-actions
            :rjNo="$dataDaftarUGD['rjNo'] ?? ''" :disabled="$isFormLocked"
            wire:key="radiologi-ugd-actions-{{ $dataDaftarUGD['rjNo'] ?? 'new' }}" />

        <div class="mt-3">
            <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.radiologi.rm-daftar-radiologi-ugd
                :rjNo="$dataDaftarUGD['rjNo'] ?? ''"
                wire:key="daftar-radiologi-ugd-{{ $dataDaftarUGD['rjNo'] ?? 'new' }}" />
        </div>
    </div>

</div>
