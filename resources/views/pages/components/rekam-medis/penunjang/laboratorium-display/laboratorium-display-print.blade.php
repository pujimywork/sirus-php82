{{-- resources/views/pages/components/rekam-medis/rekam-medis/penunjang/laboratorium-display/laboratorium-display-print.blade.php --}}

<x-pdf.layout-a4-with-out-background title="HASIL PEMERIKSAAN LABORATORIUM">

    {{-- ================================================================ --}}
    {{-- IDENTITAS PASIEN                                                   --}}
    {{-- ================================================================ --}}
    <x-slot name="patientData">
        <table cellpadding="0" cellspacing="0">
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Rekam Medis</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold">{{ $header->reg_no ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Pemeriksaan</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-mono font-bold">{{ $header->checkup_no ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tanggal Pemeriksaan</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $header->checkup_date ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Nama Pasien</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px] font-bold">{{ strtoupper($header->reg_name ?? '-') }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Jenis Kelamin</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">
                    @php
                        $sex = strtoupper($header->sex ?? '');
                    @endphp
                    {{ $sex === 'L' ? 'Laki-laki' : ($sex === 'P' ? 'Perempuan' : '-') }}
                </td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tanggal Lahir</td>
                <td class="py-0.5 text-[11px] px-1">:</td>
                <td class="py-0.5 text-[11px]">{{ $header->birth_date ?? '-' }}</td>
            </tr>
            <tr>
                <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap align-top">Alamat</td>
                <td class="py-0.5 text-[11px] px-1 align-top">:</td>
                <td class="py-0.5 text-[11px]">{{ $header->address ?? '-' }}</td>
            </tr>
        </table>
    </x-slot>

    {{-- ================================================================ --}}
    {{-- JUDUL                                                              --}}
    {{-- ================================================================ --}}
    <div class="mb-3 text-center">

        <p class="text-[10px] text-gray-500 mt-0.5">
            Dokter Pengirim: <span class="font-semibold">{{ $header->dr_name ?? '-' }}</span>
            &nbsp;|&nbsp;
            Petugas: <span class="font-semibold">{{ $header->emp_name ?? '-' }}</span>
        </p>
    </div>

    {{-- ================================================================ --}}
    {{-- HASIL LAB INTERNAL — dikelompokkan per panel                       --}}
    {{-- ================================================================ --}}
    @if (!empty($txn))
        @php
            $grouped = collect($txn)->groupBy('clab_desc');
            $sex = strtoupper($header->sex ?? 'L');
        @endphp

        @foreach ($grouped as $panelName => $items)
            <div class="mb-3">
                {{-- Panel Header --}}
                <div class="bg-gray-800 text-white text-[10px] font-bold uppercase px-2 py-0.5 mb-0">
                    {{ $panelName }}
                </div>

                <table class="w-full text-[10px] border-collapse">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-400 px-2 py-0.5 text-left w-[45%]">Pemeriksaan</th>
                            <th class="border border-gray-400 px-2 py-0.5 text-center w-[20%]">Hasil</th>
                            <th class="border border-gray-400 px-2 py-0.5 text-center w-[25%]">Nilai Normal</th>
                            <th class="border border-gray-400 px-2 py-0.5 text-center w-[10%]">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            @php
                                $itemId = trim($item->clabitem_id ?? '');
                                $itemDesc = trim($item->clabitem_desc ?? '');
                                $isHeader = $itemId === '' || str_starts_with($itemDesc, '*');

                                $useConvert = ($item->lowhigh_status ?? '') === 'Y';
                                $unitConvert = $useConvert ? (floatval($item->unit_convert ?? 1) ?: 1) : 1;
                                $unitDesc = $item->unit_desc ?? '';

                                $labResult = $useConvert
                                    ? (fmod(floatval($item->lab_result ?? 0) * $unitConvert, 1) !== 0.0
                                        ? floatval($item->lab_result) * $unitConvert
                                        : number_format(floatval($item->lab_result ?? 0) * $unitConvert))
                                    : $item->lab_result ?? '-';

                                $flagStatus = strtoupper(trim($item->lab_result_status ?? ''));
                                $isHigh = in_array($flagStatus, ['H', 'HH', 'HIGH']);
                                $isLow = in_array($flagStatus, ['L', 'LL', 'LOW']);

                                $normalLow = $sex === 'P' ? $item->low_limit_f ?? '' : $item->low_limit_m ?? '';
                                $normalHigh = $sex === 'P' ? $item->high_limit_f ?? '' : $item->high_limit_m ?? '';

                                if ($useConvert && $normalLow !== '' && $normalHigh !== '') {
                                    $normalLow =
                                        fmod(floatval($normalLow) * $unitConvert, 1) !== 0.0
                                            ? floatval($normalLow) * $unitConvert
                                            : number_format(floatval($normalLow) * $unitConvert);
                                    $normalHigh =
                                        fmod(floatval($normalHigh) * $unitConvert, 1) !== 0.0
                                            ? floatval($normalHigh) * $unitConvert
                                            : number_format(floatval($normalHigh) * $unitConvert);
                                }

                                // Nilai Normal + Satuan digabung
                                if ($normalLow !== '' && $normalHigh !== '') {
                                    $normalText = "{$normalLow} – {$normalHigh}  {$unitDesc}";
                                } elseif ($normalLow !== '' && $normalHigh === '') {
                                    $normalText = "< {$normalLow}  {$unitDesc}";
                                } elseif ($normalLow === '' && $normalHigh !== '') {
                                    $normalText = "> {$normalHigh}  {$unitDesc}";
                                } else {
                                    $normalVal = $sex === 'P' ? $item->normal_f ?? '' : $item->normal_m ?? '';
                                    $normalText = $normalVal !== '' ? "{$normalVal}  {$unitDesc}" : '-';
                                }

                                $rowBg = $isHeader
                                    ? 'bg-gray-50 font-semibold'
                                    : ($isHigh
                                        ? 'bg-red-50'
                                        : ($isLow
                                            ? 'bg-blue-50'
                                            : ''));

                                $hasilClass = $isHigh
                                    ? 'font-bold text-red-700'
                                    : ($isLow
                                        ? 'font-bold text-blue-700'
                                        : '');
                            @endphp
                            <tr class="{{ $rowBg }}">
                                <td
                                    class="border border-gray-300 px-2 py-0.5 {{ $isHeader ? 'font-semibold text-gray-700' : 'text-gray-800' }}">
                                    {{ $itemDesc }}
                                </td>
                                @if ($isHeader)
                                    <td class="border border-gray-300 px-2 py-0.5" colspan="3"></td>
                                @else
                                    <td class="border border-gray-300 px-2 py-0.5 text-center {{ $hasilClass }}">
                                        {{ $labResult }}{{ $unitDesc ? '  ' . $unitDesc : '' }}
                                    </td>
                                    <td class="border border-gray-300 px-2 py-0.5 text-center text-gray-600">
                                        {{ trim($normalText) }}
                                    </td>
                                    <td class="border border-gray-300 px-2 py-0.5 text-center">
                                        @if ($isHigh)
                                            <span class="font-bold text-red-700">Tinggi</span>
                                        @elseif ($isLow)
                                            <span class="font-bold text-blue-700">Rendah</span>
                                        @elseif ($flagStatus === 'R')
                                            <span class="font-bold text-orange-600">Abnormal</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

    {{-- ================================================================ --}}
    {{-- HASIL LAB LUAR                                                     --}}
    {{-- ================================================================ --}}
    @if (!empty($txnLuar))
        <div class="mb-3">
            <div class="bg-orange-700 text-white text-[10px] font-bold uppercase px-2 py-0.5 mb-0">
                Laboratorium Luar / Rujukan
            </div>
            <table class="w-full text-[10px] border-collapse">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-400 px-2 py-0.5 text-left w-[50%]">Pemeriksaan</th>
                        <th class="border border-gray-400 px-2 py-0.5 text-center w-[25%]">Hasil</th>
                        <th class="border border-gray-400 px-2 py-0.5 text-left w-[25%]">Nilai Normal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($txnLuar as $luar)
                        <tr>
                            <td class="border border-gray-300 px-2 py-0.5 text-gray-800">
                                {{ trim($luar->labout_desc ?? '-') }}
                            </td>
                            <td class="border border-gray-300 px-2 py-0.5 text-center font-medium text-gray-800">
                                {{ $luar->labout_result ?? '-' }}
                            </td>
                            <td class="border border-gray-300 px-2 py-0.5 text-gray-600">
                                {{ $luar->labout_normal ?? '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ================================================================ --}}
    {{-- KESIMPULAN                                                          --}}
    {{-- ================================================================ --}}
    @if (!empty($header->checkup_kesimpulan))
        <div class="p-2 mb-3 border border-gray-400 rounded">
            <p class="text-[10px] font-bold mb-0.5">Kesimpulan:</p>
            <p class="text-[10px] text-gray-800">{!! nl2br(e($header->checkup_kesimpulan)) !!}</p>
        </div>
    @endif

    {{-- ================================================================ --}}
    {{-- FOOTER: PETUGAS & DOKTER PENANGGUNG JAWAB                          --}}
    {{-- ================================================================ --}}
    @php
        // Dokter Penanggung Jawab Lab — sementara hardcode ambil dari master
        // dokter Poli LABORATORIUM (poli_id=22), aktif, urut alfabetik nama.
        // Kalau lebih dari satu dokter lab, ambil yang pertama; sesuaikan
        // logic ini saat ada mekanisme penunjukan PJ Lab yang lebih spesifik.
        $drJawabLab = \DB::table('rsmst_doctors')
            ->where('poli_id', 22)
            ->where('active_status', '1')
            ->orderBy('dr_name', 'asc')
            ->value('dr_name');
    @endphp

    <table class="w-full text-[10px] mt-4 border-collapse">
        <tr>
            {{-- Kiri: Waktu selesai --}}
            <td class="w-1/3 px-1 align-top">
                <p class="text-gray-600">Selesai Pemeriksaan:</p>
                <p class="font-semibold">{{ $header->waktu_selesai_pelayanan ?? '-' }}</p>
            </td>

            {{-- Tengah: Petugas Laboratorium --}}
            <td class="w-1/3 px-1 text-center" style="vertical-align: bottom;">
                <p class="mb-1">Petugas Laboratorium,</p>
                <div class="h-16"></div>
                <div class="inline-block min-w-[130px] border-t border-black pt-0.5">
                    <p class="font-semibold">{{ strtoupper($header->emp_name ?? '-') }}</p>
                </div>
            </td>

            {{-- Kanan: Dokter Penanggung Jawab Lab (poli_id=22, aktif). --}}
            <td class="w-1/3 px-1 text-center" style="vertical-align: bottom;">
                <p class="mb-1">Dokter Penanggung Jawab,</p>
                <div class="h-16"></div>
                <div class="inline-block min-w-[130px] border-t border-black pt-0.5">
                    <p class="font-semibold">{{ $drJawabLab ?? '-' }}</p>
                </div>
            </td>
        </tr>
    </table>

</x-pdf.layout-a4-with-out-background>
