@props([
    // 'screen' = tampilan form (Tailwind); 'print' = PDF
    'mode' => 'print',
    // Versi klausul; null = versi berlaku saat ini (PenjaminanClause::CURRENT).
    // Record lama teruskan clauseVersion tersimpan agar cetak = redaksi saat TTD.
    'version' => null,
])

@use('App\Support\PenjaminanClause')

@php
    // ── Teks ketentuan BPJS per-versi (SUMBER TUNGGAL: App\Support\PenjaminanClause) ──
    $clause = PenjaminanClause::get('bpjs', $version);
    $intro = $clause['intro'] ?? '';
    $points = $clause['points'] ?? [];
@endphp

@if ($mode === 'print')
    {{-- ══════════ MODE PRINT (PDF) ══════════ --}}
    <p class="font-bold mb-1">Ketentuan Penjaminan BPJS Kesehatan</p>
    <p class="mb-1 text-justify leading-snug">{{ $intro }}</p>
    <ol class="ml-3 list-decimal leading-snug space-y-0.5">
        @foreach ($points as $pt)
            @if (is_array($pt))
                <li>{{ $pt['text'] }}
                    <div class="ml-3 mt-0.5">
                        @foreach ($pt['sub'] as $s)
                            <div>{{ $s }}</div>
                        @endforeach
                    </div>
                </li>
            @else
                <li>{{ $pt }}</li>
            @endif
        @endforeach
    </ol>
@else
    {{-- ══════════ MODE SCREEN (form) ══════════ --}}
    <div
        class="p-4 space-y-2 text-sm border rounded-xl bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-200">
        <p class="font-semibold">Ketentuan Penjaminan BPJS Kesehatan</p>
        <p class="leading-relaxed text-justify">{{ $intro }}</p>
        <ol class="ml-4 space-y-1 list-decimal text-justify">
            @foreach ($points as $pt)
                @if (is_array($pt))
                    <li>{{ $pt['text'] }}
                        <div class="mt-0.5 ml-3 space-y-0.5">
                            @foreach ($pt['sub'] as $s)
                                <div>{{ $s }}</div>
                            @endforeach
                        </div>
                    </li>
                @else
                    <li>{{ $pt }}</li>
                @endif
            @endforeach
        </ol>
    </div>
@endif
