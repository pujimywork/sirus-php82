@props([
    // 'screen' = tampilan form (Tailwind, dark-mode); 'print' = PDF (inline/border hitam)
    'mode' => 'print',
    // ['wali'=>, 'waliHubungan'=>, 'agreement'=> '1'|'0', 'pihakInfoMedis'=>[]]
    'consent' => [],
    // Nama RS (dipakai print; screen fallback "rumah sakit ini")
    'rsName' => '',
    // Versi klausul; null = versi berlaku saat ini (GeneralConsentClause::CURRENT).
    // Record lama teruskan clauseVersion tersimpan agar cetak = redaksi saat TTD.
    'version' => null,
])

@use('App\Support\GeneralConsentClause')

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

    // ── Teks klausul per-versi (SUMBER TUNGGAL: App\Support\GeneralConsentClause) ──
    $clause = GeneralConsentClause::get('ri', $version);
    $introHtml = strtr($clause['introTemplate'] ?? '', [
        '%WALI%' => e($wali),
        '%HUB%' => e($hubunganText),
        '%RS%' => e($namaRs),
    ]);
    $agreePre = $clause['agreePre'] ?? '';
    $agreePost = $clause['agreePost'] ?? '';
    $points = $clause['points'] ?? [];
    $hakPasien = $clause['hakPasien'] ?? [];
    $tanggungJawabPasien = $clause['tanggungJawabPasien'] ?? [];
    $subtitle = $clause['subtitle'] ?? 'Pelayanan Rawat Inap';

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
    {{-- Hak & Tanggung Jawab berdampingan agar cetakan tidak memanjang ke bawah.
         Pakai <table>, bukan flex/grid — dompdf tidak mendukung keduanya. --}}
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:50%; vertical-align:top; padding-right:6px;">
                <p><strong>Hak Sebagai Pasien:</strong></p>
                <p style="padding-left: 12px;">
                    @foreach ($hakPasien as $i => $hak)
                        {{ $i + 1 }}. {!! $hak !!}@if (!$loop->last)<br>@endif
                    @endforeach
                </p>
            </td>
            <td style="width:50%; vertical-align:top; padding-left:6px;">
                <p><strong>Tanggung Jawab Sebagai Pasien:</strong></p>
                <p style="padding-left: 12px;">
                    @foreach ($tanggungJawabPasien as $i => $tj)
                        {{ $i + 1 }}. {!! $tj !!}@if (!$loop->last)<br>@endif
                    @endforeach
                </p>
            </td>
        </tr>
    </table>
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
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
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

        {{-- Berdampingan agar tidak memanjang ke bawah; menumpuk di layar sempit. --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-1">
                <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Hak Sebagai Pasien</h4>
                <ol
                    class="pl-5 space-y-1 text-sm leading-relaxed text-justify text-gray-700 list-decimal dark:text-gray-300">
                    @foreach ($hakPasien as $hak)
                        <li>{!! $hak !!}</li>
                    @endforeach
                </ol>
            </div>

            <div class="space-y-1">
                <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Tanggung Jawab Sebagai Pasien</h4>
                <ol
                    class="pl-5 space-y-1 text-sm leading-relaxed text-justify text-gray-700 list-decimal dark:text-gray-300">
                    @foreach ($tanggungJawabPasien as $tj)
                        <li>{!! $tj !!}</li>
                    @endforeach
                </ol>
            </div>
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
