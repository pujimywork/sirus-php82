<div class="w-full mb-1 space-y-4">

    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Laboratorium</h3>
        <livewire:pages::transaksi.rj.emr-rj.pemeriksaan.penunjang.laborat.rm-laborat-rj-actions
            :rjNo="$dataDaftarPoliRJ['rjNo'] ?? ''" :disabled="$isFormLocked"
            wire:key="laborat-actions-{{ $dataDaftarPoliRJ['rjNo'] ?? 'new' }}" />

        <div class="mt-3">
            <livewire:pages::transaksi.rj.emr-rj.pemeriksaan.penunjang.laborat.rm-daftar-laborat-rj
                :rjNo="$dataDaftarPoliRJ['rjNo'] ?? ''"
                wire:key="daftar-laborat-rj-{{ $dataDaftarPoliRJ['rjNo'] ?? 'new' }}" />
        </div>
    </div>

    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Laboratorium Luar</h3>
        <livewire:pages::transaksi.rj.emr-rj.pemeriksaan.penunjang.laborat.rm-laborat-luar-rj-actions
            :rjNo="$dataDaftarPoliRJ['rjNo'] ?? ''" :disabled="$isFormLocked"
            wire:key="laborat-luar-actions-{{ $dataDaftarPoliRJ['rjNo'] ?? 'new' }}" />

        <div class="mt-3">
            <livewire:pages::transaksi.rj.emr-rj.pemeriksaan.penunjang.laborat.rm-daftar-laborat-luar-rj
                :rjNo="$dataDaftarPoliRJ['rjNo'] ?? ''"
                wire:key="daftar-lab-luar-rj-{{ $dataDaftarPoliRJ['rjNo'] ?? 'new' }}" />
        </div>
    </div>

    <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Radiologi</h3>
        <livewire:pages::transaksi.rj.emr-rj.pemeriksaan.penunjang.radiologi.rm-radiologi-rj-actions
            :rjNo="$dataDaftarPoliRJ['rjNo'] ?? ''" :disabled="$isFormLocked"
            wire:key="radiologi-actions-{{ $dataDaftarPoliRJ['rjNo'] ?? 'new' }}" />

        <div class="mt-3">
            <livewire:pages::transaksi.rj.emr-rj.pemeriksaan.penunjang.radiologi.rm-daftar-radiologi-rj
                :rjNo="$dataDaftarPoliRJ['rjNo'] ?? ''"
                wire:key="daftar-radiologi-rj-{{ $dataDaftarPoliRJ['rjNo'] ?? 'new' }}" />
        </div>
    </div>

</div>
