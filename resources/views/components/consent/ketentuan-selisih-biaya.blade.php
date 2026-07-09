@props([
    // 'screen' = tampilan form (Tailwind); 'print' = PDF
    'mode' => 'print',
    // Versi klausul; null = versi berlaku saat ini (PenjaminanClause::CURRENT).
    // Record lama teruskan clauseVersion tersimpan agar cetak = redaksi saat TTD.
    'version' => null,
])

@use('App\Support\PenjaminanClause')

@php
    // ── Ketentuan selisih biaya naik kelas per-versi (SUMBER TUNGGAL: App\Support\PenjaminanClause) ──
    $rows = PenjaminanClause::get('selisih', $version)['rows'] ?? [];
@endphp

@if ($mode === 'print')
    {{-- ══════════ MODE PRINT (PDF) ══════════ --}}
    <p class="font-bold mb-1">Ketentuan Selisih Biaya (Naik Kelas)</p>
    <table class="w-full border-collapse leading-snug">
        <thead>
            <tr>
                <th class="border border-black px-2 py-1 text-left" style="width: 34%;">Jenis Perawatan</th>
                <th class="border border-black px-2 py-1 text-left">Ketentuan Selisih Biaya</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td class="border border-black px-2 py-1 align-top">{{ $r['jenis'] }}</td>
                    <td class="border border-black px-2 py-1 align-top text-justify">{{ $r['ketentuan'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    {{-- ══════════ MODE SCREEN (form) ══════════ --}}
    <div class="space-y-2 text-sm">
        <p class="font-semibold text-amber-900 dark:text-amber-200">Ketentuan Selisih Biaya (Naik Kelas)</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border border-amber-200 rounded-lg dark:border-amber-700">
                <thead class="text-left bg-amber-100/60 dark:bg-amber-900/30 text-amber-900 dark:text-amber-200">
                    <tr>
                        <th class="px-3 py-2 font-semibold border-b border-amber-200 dark:border-amber-700"
                            style="width: 34%;">Jenis Perawatan</th>
                        <th class="px-3 py-2 font-semibold border-b border-amber-200 dark:border-amber-700">
                            Ketentuan Selisih Biaya</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-200/60 dark:divide-amber-700/50 text-amber-900 dark:text-amber-100">
                    @foreach ($rows as $r)
                        <tr>
                            <td class="px-3 py-2 font-medium align-top">{{ $r['jenis'] }}</td>
                            <td class="px-3 py-2 align-top text-justify">{{ $r['ketentuan'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
