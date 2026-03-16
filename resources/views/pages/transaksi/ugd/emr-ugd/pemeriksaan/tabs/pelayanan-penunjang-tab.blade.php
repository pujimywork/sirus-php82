{{-- pages/transaksi/ugd/emr-ugd/pemeriksaan/tabs/pelayanan-penunjang-tab.blade.php --}}
<div class="w-full mb-1">
    <div class="pt-0">

        {{-- Lab --}}
        <div class="mb-4">
            <div>
                <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.laborat.rm-laborat-ugd-actions
                    :rjNo="$dataDaftarUGD['rjNo'] ?? ''" :disabled="$isFormLocked"
                    wire:key="laborat-ugd-actions-{{ $dataDaftarUGD['rjNo'] ?? 'new' }}" />
            </div>

            <table class="w-full text-sm text-left text-gray-500 table-auto">
                <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">No Lab</th>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Tgl Lab</th>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Pemeriksaan
                            Lab</th>
                        <th
                            class="w-8 px-4 py-3 text-xs font-medium text-center text-gray-500 uppercase dark:text-gray-400">
                            Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    @isset($dataDaftarUGD['pemeriksaan']['pemeriksaanPenunjang']['lab'])
                        @foreach ($dataDaftarUGD['pemeriksaan']['pemeriksaanPenunjang']['lab'] as $pemeriksaanPenunjangLab)
                            <tr class="border-b group">
                                <td
                                    class="w-1/4 px-2 py-2 font-normal text-gray-700 group-hover:bg-gray-50 whitespace-nowrap">
                                    {{ $pemeriksaanPenunjangLab['labHdr']['labHdrNo'] ?? '' }}
                                </td>
                                <td
                                    class="w-1/4 px-2 py-2 font-normal text-gray-700 group-hover:bg-gray-50 whitespace-nowrap">
                                    {{ $pemeriksaanPenunjangLab['labHdr']['labHdrDate'] ?? '' }}
                                </td>
                                <td
                                    class="w-1/4 px-2 py-2 font-normal text-gray-700 group-hover:bg-gray-50 whitespace-nowrap">
                                    {{ isset($pemeriksaanPenunjangLab['labHdr']['labDtl'])
                                        ? implode(', ', array_column($pemeriksaanPenunjangLab['labHdr']['labDtl'], 'clabitem_desc'))
                                        : '' }}
                                </td>
                                <td
                                    class="w-1/4 px-2 py-2 font-normal text-gray-700 group-hover:bg-gray-50 whitespace-nowrap">
                                    -</td>
                            </tr>
                        @endforeach
                    @endisset
                </tbody>
            </table>
        </div>

        {{-- Radiologi --}}
        <div class="mb-4">
            <div>
                <livewire:pages::transaksi.ugd.emr-ugd.pemeriksaan.penunjang.radiologi.rm-radiologi-ugd-actions
                    :rjNo="$dataDaftarUGD['rjNo'] ?? ''" :disabled="$isFormLocked"
                    wire:key="radiologi-ugd-actions-{{ $dataDaftarUGD['rjNo'] ?? 'new' }}" />
            </div>

            <table class="w-full text-sm text-left text-gray-500 table-auto">
                <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">No Rad</th>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Tgl Rad
                        </th>
                        <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Pemeriksaan
                            Rad</th>
                        <th
                            class="w-8 px-4 py-3 text-xs font-medium text-center text-gray-500 uppercase dark:text-gray-400">
                            Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    @isset($dataDaftarUGD['pemeriksaan']['pemeriksaanPenunjang']['rad'])
                        @foreach ($dataDaftarUGD['pemeriksaan']['pemeriksaanPenunjang']['rad'] as $pemeriksaanPenunjangRad)
                            <tr class="border-b group">
                                <td
                                    class="w-1/4 px-2 py-2 font-normal text-gray-700 group-hover:bg-gray-50 whitespace-nowrap">
                                    {{ $pemeriksaanPenunjangRad['radHdr']['radHdrNo'] ?? '-' }}
                                </td>
                                <td
                                    class="w-1/4 px-2 py-2 font-normal text-gray-700 group-hover:bg-gray-50 whitespace-nowrap">
                                    {{ $pemeriksaanPenunjangRad['radHdr']['radHdrDate'] ?? '-' }}
                                </td>
                                <td
                                    class="w-1/4 px-2 py-2 font-normal text-gray-700 group-hover:bg-gray-50 whitespace-nowrap">
                                    {{ isset($pemeriksaanPenunjangRad['radHdr']['radDtl'])
                                        ? implode(', ', array_column($pemeriksaanPenunjangRad['radHdr']['radDtl'], 'rad_desc'))
                                        : '-' }}
                                </td>
                                <td
                                    class="w-1/4 px-2 py-2 font-normal text-gray-700 group-hover:bg-gray-50 whitespace-nowrap">
                                    -</td>
                            </tr>
                        @endforeach
                    @endisset
                </tbody>
            </table>
        </div>

    </div>
</div>
