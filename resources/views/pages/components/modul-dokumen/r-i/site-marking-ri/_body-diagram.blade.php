{{--
    Partial diagram penanda lokasi operasi (dipakai editor & cetak).
    Param:
      $marks      array  — [['view'=>panelId,'x'=>%,'y'=>%], ...]  (x/y = persen 0..100 relatif panel)
      $clickable  bool   — true: emit handler klik ($wire.addMark) + cursor crosshair (mode editor)
                           false: statis + hanya tampilkan panel yang punya tanda (mode cetak)
--}}
@php
    $clickable = $clickable ?? false;
    $stroke = '#9ca3af';
    $fill = '#e5e7eb';

    // ── Bentuk dasar (reusable) ──
    $bodyPath = 'M60 8 c-7 0 -12 5 -12 12 c0 5 2 8 5 10 c-1 3 -2 5 -5 6 c-8 2 -14 7 -16 15 l-6 34 c-1 4 5 6 7 1 l5 -22 c1 6 1 10 0 16 l-2 30 c-1 8 0 16 1 24 l2 40 l-3 40 c-1 6 8 7 9 1 l4 -38 l3 -34 l3 34 l4 38 c1 6 10 5 9 -1 l-3 -40 l2 -40 c1 -8 2 -16 1 -24 l-2 -30 c-1 -6 -1 -10 0 -16 l5 22 c2 5 8 3 7 -1 l-6 -34 c-2 -8 -8 -13 -16 -15 c-3 -1 -4 -3 -5 -6 c3 -2 5 -5 5 -10 c0 -7 -5 -12 -12 -12 z';

    $body = fn($back) =>
        '<path d="' . $bodyPath . '" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        ($back
            ? '<line x1="60" y1="44" x2="60" y2="150" stroke="' . $stroke . '" stroke-width="1" stroke-dasharray="3 3"/>'
            : '');

    $headFront =
        '<ellipse cx="50" cy="58" rx="30" ry="40" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<ellipse cx="19" cy="58" rx="5" ry="9" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<ellipse cx="81" cy="58" rx="5" ry="9" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<circle cx="40" cy="52" r="2.5" fill="' . $stroke . '"/>' .
        '<circle cx="60" cy="52" r="2.5" fill="' . $stroke . '"/>' .
        '<path d="M50 55 L50 65" fill="none" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<path d="M44 72 Q50 76 56 72" fill="none" stroke="' . $stroke . '" stroke-width="1.5"/>';

    $headBack =
        '<ellipse cx="50" cy="58" rx="30" ry="40" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<path d="M24 44 Q50 24 76 44" fill="none" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<ellipse cx="19" cy="58" rx="5" ry="9" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<ellipse cx="81" cy="58" rx="5" ry="9" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>';

    // Tangan kanan (telapak/punggung sama bentuk). vb 100x140
    $hand =
        '<rect x="30" y="62" width="46" height="58" rx="12" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<rect x="33" y="20" width="10" height="50" rx="5" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<rect x="45" y="10" width="10" height="60" rx="5" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<rect x="57" y="16" width="10" height="54" rx="5" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<rect x="68" y="30" width="9" height="42" rx="4.5" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<rect x="12" y="66" width="12" height="34" rx="6" transform="rotate(-32 18 83)" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<rect x="38" y="112" width="30" height="20" rx="6" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>';

    // Kaki kanan (telapak/punggung sama bentuk). vb 80x150
    $foot =
        '<path d="M40 146 C28 146 23 136 24 120 L26 60 C27 46 33 40 44 41 C55 42 58 50 57 62 L55 120 C55 136 52 146 40 146 Z" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<circle cx="30" cy="32" r="7" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<circle cx="42" cy="27" r="5" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<circle cx="50" cy="28" r="4.5" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<circle cx="57" cy="31" r="4" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>' .
        '<circle cx="63" cy="35" r="3.5" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1.5"/>';

    // mirror utk sisi kiri
    $mirror = fn($inner, $vbw) => '<g transform="scale(-1,1) translate(-' . $vbw . ',0)">' . $inner . '</g>';

    // ── Definisi panel: grup => [ [id,label,vbw,vbh,w,svg], ... ] ──
    $groups = [
        'Tubuh' => [
            ['id' => 'bodyFront', 'label' => 'Depan', 'vbw' => 120, 'vbh' => 280, 'w' => 120, 'svg' => $body(false)],
            ['id' => 'bodyBack', 'label' => 'Belakang', 'vbw' => 120, 'vbh' => 280, 'w' => 120, 'svg' => $body(true)],
        ],
        'Kepala' => [
            ['id' => 'headFront', 'label' => 'Depan', 'vbw' => 100, 'vbh' => 120, 'w' => 95, 'svg' => $headFront],
            ['id' => 'headBack', 'label' => 'Belakang', 'vbw' => 100, 'vbh' => 120, 'w' => 95, 'svg' => $headBack],
        ],
        'Tangan' => [
            ['id' => 'handRPalm', 'label' => 'Kanan — Telapak', 'vbw' => 100, 'vbh' => 140, 'w' => 80, 'svg' => $hand],
            ['id' => 'handRDorsum', 'label' => 'Kanan — Punggung', 'vbw' => 100, 'vbh' => 140, 'w' => 80, 'svg' => $hand],
            ['id' => 'handLPalm', 'label' => 'Kiri — Telapak', 'vbw' => 100, 'vbh' => 140, 'w' => 80, 'svg' => $mirror($hand, 100)],
            ['id' => 'handLDorsum', 'label' => 'Kiri — Punggung', 'vbw' => 100, 'vbh' => 140, 'w' => 80, 'svg' => $mirror($hand, 100)],
        ],
        'Kaki' => [
            ['id' => 'footRSole', 'label' => 'Kanan — Telapak', 'vbw' => 80, 'vbh' => 150, 'w' => 64, 'svg' => $foot],
            ['id' => 'footRDorsum', 'label' => 'Kanan — Punggung', 'vbw' => 80, 'vbh' => 150, 'w' => 64, 'svg' => $foot],
            ['id' => 'footLSole', 'label' => 'Kiri — Telapak', 'vbw' => 80, 'vbh' => 150, 'w' => 64, 'svg' => $mirror($foot, 80)],
            ['id' => 'footLDorsum', 'label' => 'Kiri — Punggung', 'vbw' => 80, 'vbh' => 150, 'w' => 64, 'svg' => $mirror($foot, 80)],
        ],
    ];

    // hitung tanda per panel
    $countByView = collect($marks ?? [])->groupBy('view')->map->count();
@endphp

<div class="space-y-4">
    @foreach ($groups as $groupName => $panels)
        @php
            // mode cetak: skip grup yang tidak punya tanda sama sekali
            $visiblePanels = $clickable
                ? $panels
                : array_values(array_filter($panels, fn($p) => ($countByView[$p['id']] ?? 0) > 0));
        @endphp
        @if (count($visiblePanels) > 0)
            <div>
                <p class="mb-1 text-sm font-semibold text-body dark:text-gray-300">{{ $groupName }}</p>
                <div style="text-align:center">
                    @foreach ($visiblePanels as $p)
                        @php
                            $h = round($p['w'] * $p['vbh'] / $p['vbw']);
                            $r = round($p['vbw'] * 0.04, 1);
                            $fs = round($r * 1.3, 1);
                        @endphp
                        <div style="display:inline-block; vertical-align:top; margin:4px 8px; text-align:center">
                            <div class="text-xs font-medium text-muted dark:text-gray-400" style="margin-bottom:2px">
                                {{ $p['label'] }}</div>
                            <svg viewBox="0 0 {{ $p['vbw'] }} {{ $p['vbh'] }}" width="{{ $p['w'] }}"
                                height="{{ $h }}"
                                class="bg-surface-soft border border-hairline rounded-lg dark:bg-gray-800 dark:border-gray-700 {{ $clickable ? 'cursor-crosshair' : '' }}"
                                style="touch-action:none"
                                @if ($clickable) x-on:click="const _r = $el.getBoundingClientRect(); $wire.addMark('{{ $p['id'] }}', ($event.clientX - _r.left) / _r.width * 100, ($event.clientY - _r.top) / _r.height * 100)" @endif>
                                {!! $p['svg'] !!}
                                @php $n = 0; @endphp
                                @foreach ($marks ?? [] as $m)
                                    @if (($m['view'] ?? '') === $p['id'])
                                        @php
                                            $n++;
                                            $cx = round(($m['x'] / 100) * $p['vbw'], 2);
                                            $cy = round(($m['y'] / 100) * $p['vbh'], 2);
                                        @endphp
                                        <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="#dc2626"
                                            stroke="#ffffff" stroke-width="1.2" />
                                        <text x="{{ $cx }}" y="{{ $cy + $fs * 0.35 }}" text-anchor="middle"
                                            font-size="{{ $fs }}" font-weight="bold" fill="#ffffff">{{ $n }}</text>
                                    @endif
                                @endforeach
                            </svg>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>
