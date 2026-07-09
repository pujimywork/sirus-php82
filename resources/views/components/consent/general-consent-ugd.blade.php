@props([
    // 'screen' = tampilan form (Tailwind, dark-mode); 'print' = PDF (inline/border hitam)
    'mode' => 'print',
    // ['wali'=>, 'waliHubungan'=>, 'agreement'=> '1'|'0', 'pihakInfoMedis'=>[]]
    'consent' => [],
    // Nama RS (dipakai print; screen fallback "rumah sakit ini")
    'rsName' => '',
])

@php
    $hubunganMap = [
        'pasien' => 'Pasien Sendiri',
        'suami' => 'Suami',
        'istri' => 'Istri',
        'ayah' => 'Ayah',
        'ibu' => 'Ibu',
        'anak' => 'Anak',
        'saudara' => 'Saudara',
        'wali_hukum' => 'Wali Hukum',
        'lainnya' => 'Lainnya',
    ];
    $wali = strtoupper($consent['wali'] ?? '-');
    $hubunganText = $hubunganMap[$consent['waliHubungan'] ?? ''] ?? '-';
    $setuju = ($consent['agreement'] ?? '1') === '1';
    $agreementText = $setuju ? 'SETUJU' : 'TIDAK SETUJU';
    $namaRs = trim($rsName) !== '' ? $rsName : 'rumah sakit ini';

    // ─────────────────────────────────────────────────────────────
    // SUMBER TUNGGAL TEKS — dipakai mode screen & print (redaksi identik).
    // Acuan: cetak (dokumen bertanda tangan). Konteks: UNIT GAWAT DARURAT.
    // ─────────────────────────────────────────────────────────────
    $introHtml =
        'Saya yang bertanda tangan di bawah ini, <strong>' .
        e($wali) .
        '</strong> (sebagai <strong>' .
        e($hubunganText) .
        '</strong> pasien), menyatakan bahwa saya telah mendapat penjelasan yang cukup mengenai tujuan, prosedur, risiko, dan manfaat dari pelayanan medis yang akan diberikan di <strong>' .
        e($namaRs) .
        '</strong>, dengan bahasa yang saya pahami.';
    $agreePre = 'Dengan ini saya menyatakan ';
    $agreePost =
        ' untuk menerima pelayanan kesehatan, pemeriksaan, dan tindakan yang diperlukan sesuai dengan standar pelayanan medis yang berlaku di rumah sakit ini.';
    $points = [
        'Saya berhak mendapat informasi yang jelas mengenai kondisi kesehatan, diagnosis, prosedur, risiko, dan alternatif tindakan.',
        'Saya berhak menolak/menghentikan tindakan, termasuk pelayanan resusitasi, setelah mendapat penjelasan.',
        'Saya berhak meminta konsultasi dokter lain (<em>second opinion</em>) bila diperlukan.',
        'Rumah sakit menjaga kerahasiaan informasi medis saya sesuai ketentuan yang berlaku.',
        'Saya bertanggung jawab atas biaya pelayanan sesuai ketentuan rumah sakit.',
        'Untuk tindakan invasif, pembedahan, anestesi, transfusi darah, dan tindakan berisiko tinggi akan diminta <em>persetujuan tindakan (informed consent)</em> tersendiri. Dalam keadaan darurat yang mengancam nyawa, tindakan penyelamatan dapat dilakukan sebelum persetujuan diperoleh.',
    ];
    $pihakList = collect($consent['pihakInfoMedis'] ?? [])->filter(fn($p) => !empty(trim($p['nama'] ?? '')));
@endphp

@if ($mode === 'print')
    {{-- ══════════ MODE PRINT (PDF) ══════════ --}}
    <p>{!! $introHtml !!}</p>
    <br>
    <p>{{ $agreePre }}<strong
            class="{{ $setuju ? 'font-bold text-green-700' : 'font-bold text-red-700' }}">{{ $agreementText }}</strong>{{ $agreePost }}
    </p>
    <br>
    <p>Saya memahami bahwa:</p>
    <p style="padding-left: 12px;">
        @foreach ($points as $i => $pt)
            {{ $i + 1 }}. {!! $pt !!}@if (!$loop->last)<br>@endif
        @endforeach
    </p>
    <br>
    <p><strong>Pihak yang Diberi Akses Informasi Medis:</strong></p>
    @if ($pihakList->count() > 0)
        <table class="w-full mt-1 text-[9px] border-collapse">
            <thead>
                <tr>
                    <th class="border border-black px-1 py-0.5 w-6">No</th>
                    <th class="border border-black px-1 py-0.5">Nama</th>
                    <th class="border border-black px-1 py-0.5">Hubungan</th>
                    <th class="border border-black px-1 py-0.5">No. HP</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pihakList as $index => $pihak)
                    <tr>
                        <td class="border border-black px-1 py-0.5 text-center">{{ $index + 1 }}</td>
                        <td class="border border-black px-1 py-0.5">{{ $pihak['nama'] ?? '-' }}</td>
                        <td class="border border-black px-1 py-0.5">{{ $pihak['hubungan'] ?? '-' }}</td>
                        <td class="border border-black px-1 py-0.5">{{ $pihak['noHp'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="padding-left:12px;"><em>Belum ada pihak yang ditunjuk.</em></p>
    @endif
@else
    {{-- ══════════ MODE SCREEN (form / tampilan) ══════════ --}}
    <div
        class="p-5 mb-4 space-y-3 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="text-center">
            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
                Formulir Persetujuan Umum (General Consent)
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pelayanan Unit Gawat Darurat</p>
        </div>

        <p class="text-sm leading-relaxed text-justify text-gray-700 dark:text-gray-300">{!! $introHtml !!}</p>
        <p class="text-sm leading-relaxed text-justify text-gray-700 dark:text-gray-300">
            {{ $agreePre }}<strong
                class="{{ $setuju ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">{{ $agreementText }}</strong>{{ $agreePost }}
        </p>

        <div class="space-y-1">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Saya memahami bahwa:</h4>
            <ol
                class="pl-5 space-y-1 text-sm leading-relaxed text-justify text-gray-700 list-decimal dark:text-gray-300">
                @foreach ($points as $pt)
                    <li>{!! $pt !!}</li>
                @endforeach
            </ol>
        </div>

        <div class="pt-3 space-y-2 border-t border-gray-100 dark:border-gray-800">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                Pihak yang Diberi Akses Informasi Medis
            </h4>
            @if (trim($slot) !== '')
                {{ $slot }}
            @elseif ($pihakList->count() > 0)
                <table class="w-full mt-2 text-sm border border-gray-200 rounded-lg dark:border-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr class="text-left text-gray-600 dark:text-gray-300">
                            <th class="px-3 py-1.5 border-b w-10 text-center">#</th>
                            <th class="px-3 py-1.5 border-b">Nama</th>
                            <th class="px-3 py-1.5 border-b">Hubungan</th>
                            <th class="px-3 py-1.5 border-b">No. HP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pihakList as $i => $row)
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <td class="px-3 py-1.5 text-center text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-3 py-1.5 font-medium text-gray-800 dark:text-gray-200">
                                    {{ $row['nama'] ?? '-' }}</td>
                                <td class="px-3 py-1.5 text-gray-600 dark:text-gray-400">{{ $row['hubungan'] ?? '-' }}
                                </td>
                                <td class="px-3 py-1.5 text-gray-600 dark:text-gray-400">{{ $row['noHp'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-sm italic text-gray-400">Belum ada pihak yang ditunjuk.</p>
            @endif
        </div>
    </div>
@endif
