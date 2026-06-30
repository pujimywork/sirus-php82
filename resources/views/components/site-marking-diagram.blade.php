{{--
    <x-site-marking-diagram> — Diagram Penanda Lokasi Operasi (reusable).

    Sumber gambar = 2 sheet SVG vektor "Formulir Penandaan Area Operasi" di
    resources/views/components/site-marking/sheets/{bodies,parts}.blade.php.
    Tiap panel adalah "jendela" (viewBox window) dari sheet → 16 figur terpisah,
    tetap garis vektor mulus (tanpa re-trace). Portable: salin folder
    components/site-marking* + komponen ini ke aplikasi lain.

    Props:
      :marks       array   — [['view'=>panelId,'x'=>%,'y'=>%], ...] (x/y persen 0..100 relatif window panel)
      :editable    bool    — true: panel bisa diklik (butuh konteks Livewire); false: statis (cetak/preview)
      wire-add-mark string — nama method Livewire dipanggil saat klik: method($view,$x,$y). default 'addMark'
--}}
@props([
    'marks' => [],
    'editable' => false,
    'wireAddMark' => 'addMark',
])

@php
    $sheetBase = 'components.site-marking.sheets.';

    // id => [label, sheet, vx, vy, vw, vh, w(px tampil)] ; vx..vh = window viewBox pada sheet
    $groups = [
        'Tubuh' => [
            ['id' => 'priaFront', 'label' => 'Pria — Depan', 'sheet' => 'bodies', 'vx' => 4, 'vy' => 14, 'vw' => 144, 'vh' => 360, 'w' => 150],
            ['id' => 'priaBack', 'label' => 'Pria — Belakang', 'sheet' => 'bodies', 'vx' => 152, 'vy' => 14, 'vw' => 146, 'vh' => 360, 'w' => 150],
            ['id' => 'wanitaFront', 'label' => 'Wanita — Depan', 'sheet' => 'bodies', 'vx' => 303, 'vy' => 14, 'vw' => 146, 'vh' => 360, 'w' => 150],
            ['id' => 'wanitaBack', 'label' => 'Wanita — Belakang', 'sheet' => 'bodies', 'vx' => 452, 'vy' => 14, 'vw' => 144, 'vh' => 360, 'w' => 150],
        ],
        'Tangan' => [
            ['id' => 'handPalmKiri', 'label' => 'Kiri — Telapak', 'sheet' => 'parts', 'vx' => 6, 'vy' => 12, 'vw' => 84, 'vh' => 118, 'w' => 120],
            ['id' => 'handPalmKanan', 'label' => 'Kanan — Telapak', 'sheet' => 'parts', 'vx' => 98, 'vy' => 12, 'vw' => 84, 'vh' => 118, 'w' => 120],
            ['id' => 'handDorsumKiri', 'label' => 'Kiri — Punggung', 'sheet' => 'parts', 'vx' => 192, 'vy' => 12, 'vw' => 84, 'vh' => 118, 'w' => 120],
            ['id' => 'handDorsumKanan', 'label' => 'Kanan — Punggung', 'sheet' => 'parts', 'vx' => 282, 'vy' => 12, 'vw' => 78, 'vh' => 118, 'w' => 116],
        ],
        'Kaki' => [
            ['id' => 'footPalmKanan', 'label' => 'Kanan — Telapak', 'sheet' => 'parts', 'vx' => 364, 'vy' => 12, 'vw' => 66, 'vh' => 118, 'w' => 96],
            ['id' => 'footPalmKiri', 'label' => 'Kiri — Telapak', 'sheet' => 'parts', 'vx' => 430, 'vy' => 12, 'vw' => 62, 'vh' => 118, 'w' => 94],
            ['id' => 'footDorsumKiri', 'label' => 'Kiri — Punggung', 'sheet' => 'parts', 'vx' => 496, 'vy' => 12, 'vw' => 52, 'vh' => 118, 'w' => 86],
            ['id' => 'footDorsumKanan', 'label' => 'Kanan — Punggung', 'sheet' => 'parts', 'vx' => 549, 'vy' => 12, 'vw' => 49, 'vh' => 118, 'w' => 84],
        ],
        'Kepala' => [
            ['id' => 'headFront', 'label' => 'Depan', 'sheet' => 'parts', 'vx' => 6, 'vy' => 138, 'vw' => 174, 'vh' => 140, 'w' => 180],
            ['id' => 'headBack', 'label' => 'Belakang', 'sheet' => 'parts', 'vx' => 185, 'vy' => 138, 'vw' => 175, 'vh' => 140, 'w' => 180],
            ['id' => 'headProfileKiri', 'label' => 'Profil Kiri', 'sheet' => 'parts', 'vx' => 366, 'vy' => 138, 'vw' => 128, 'vh' => 140, 'w' => 150],
            ['id' => 'headProfileKanan', 'label' => 'Profil Kanan', 'sheet' => 'parts', 'vx' => 494, 'vy' => 138, 'vw' => 104, 'vh' => 140, 'w' => 132],
        ],
    ];

    $countByView = collect($marks ?? [])->groupBy('view')->map->count();
@endphp

<div {{ $attributes->class('w-full overflow-x-auto') }}>
    <div class="space-y-4 min-w-fit">
        @foreach ($groups as $groupName => $panels)
            @php
                $visiblePanels = $editable
                    ? $panels
                    : array_values(array_filter($panels, fn($p) => ($countByView[$p['id']] ?? 0) > 0));
            @endphp
            @if (count($visiblePanels) > 0)
                <div>
                    <p class="mb-1 text-sm font-semibold text-body dark:text-gray-300">{{ $groupName }}</p>
                    <div style="text-align:center">
                        @foreach ($visiblePanels as $p)
                            @php
                                $h = round($p['w'] * $p['vh'] / $p['vw']);
                                $r = round($p['vw'] * 0.05, 1);
                                $fs = round($r * 1.25, 1);
                            @endphp
                            <div style="display:inline-block; vertical-align:top; margin:4px 8px; text-align:center">
                                <div class="text-xs font-medium text-muted dark:text-gray-400" style="margin-bottom:2px">
                                    {{ $p['label'] }}</div>
                                <svg viewBox="{{ $p['vx'] }} {{ $p['vy'] }} {{ $p['vw'] }} {{ $p['vh'] }}"
                                    width="{{ $p['w'] }}" height="{{ $h }}" preserveAspectRatio="xMidYMid meet"
                                    class="bg-white border border-hairline rounded-lg {{ $editable ? 'cursor-crosshair' : '' }}"
                                    style="touch-action:none; max-width:100%; height:auto; display:inline-block"
                                    @if ($editable) x-on:click="const _r = $el.getBoundingClientRect(); $wire.{{ $wireAddMark }}('{{ $p['id'] }}', ($event.clientX - _r.left) / _r.width * 100, ($event.clientY - _r.top) / _r.height * 100)" @endif>
                                    @include($sheetBase . $p['sheet'])
                                    @php $n = 0; @endphp
                                    @foreach ($marks ?? [] as $m)
                                        @if (($m['view'] ?? '') === $p['id'])
                                            @php
                                                $n++;
                                                $cx = round($p['vx'] + ($m['x'] / 100) * $p['vw'], 2);
                                                $cy = round($p['vy'] + ($m['y'] / 100) * $p['vh'], 2);
                                            @endphp
                                            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="#dc2626"
                                                stroke="#ffffff" stroke-width="1.4" />
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
</div>
