@props([
    // 'screen' = tampilan form (Tailwind); 'print' = PDF
    'mode' => 'print',
])

@php
    // ── SUMBER TUNGGAL teks ketentuan BPJS (dipakai form & cetak) ──
    $intro =
        'BPJS Kesehatan hanya menjamin pelayanan kesehatan peserta JKN yang sesuai dengan ketentuan yang berlaku. Pelayanan yang tidak sesuai tidak menjadi tanggungan BPJS Kesehatan, antara lain:';
    // Poin bernomor; item bertipe array = punya sub-poin (a/b).
    $points = [
        'Pelayanan di luar ketentuan/prosedur yang diatur dalam Program JKN.',
        [
            'text' => 'Pelayanan yang tidak sesuai ketentuan:',
            'sub' => [
                'a. Rawat jalan/rawat inap atas permintaan sendiri (APS).',
                'b. Penolakan/tidak mematuhi rencana terapi yang direkomendasikan (pulang APS) dan menerima segala konsekuensi atas keputusan pribadinya.',
            ],
        ],
        'Pelayanan di luar lingkup penjaminan dalam Perjanjian Kerja Sama.',
        'Pelayanan homecare di rumah (tidak dijamin dalam PKS FKRTL).',
        'Kecelakaan lalu lintas tidak sesuai ketentuan (tidak urus LP/damai, intoksikasi miras).',
        'Pelayanan atas instruksi dari fasilitas kesehatan yang tidak bekerja sama dengan BPJS Kesehatan.',
        'Apabila peserta memilih pelayanan di luar ketentuan di atas, biaya menjadi tanggungan pribadi/keluarga.',
    ];
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
