{{--
    <x-site-marking-diagram> — Diagram Penanda Lokasi Operasi (reusable).

    16 figur SVG vektor "Formulir Penandaan Area Operasi", satu file per figur di
    resources/views/components/site-marking/figs/<id>.blade.php (viewBox 0 0 210 297).
    Portable: salin folder components/site-marking* + komponen ini ke aplikasi lain.

    Props:
      :marks       array   — [['view'=>panelId,'x'=>%,'y'=>%], ...] (x/y persen 0..100 relatif panel)
      :editable    bool    — true: panel bisa diklik (butuh konteks Livewire); false: statis (cetak/preview)
      wire-add-mark string — nama method Livewire dipanggil saat klik: method($view,$x,$y). default 'addMark'
--}}
@props([
    'marks' => [],
    'editable' => false,
    'wireAddMark' => 'addMark',
])

@php
    $figBase = 'components.site-marking.figs.';
    $VBW = 210;
    $VBH = 297; // viewBox semua figur

    // id => [label, w(px tampil)]
    $groups = [
        'Tubuh' => [
            ['id' => 'priaFront', 'label' => 'Pria — Depan', 'w' => 220],
            ['id' => 'priaBack', 'label' => 'Pria — Belakang', 'w' => 220],
            ['id' => 'wanitaFront', 'label' => 'Wanita — Depan', 'w' => 220],
            ['id' => 'wanitaBack', 'label' => 'Wanita — Belakang', 'w' => 220],
        ],
        'Tangan' => [
            ['id' => 'handPalmKiri', 'label' => 'Kiri — Telapak', 'w' => 185],
            ['id' => 'handPalmKanan', 'label' => 'Kanan — Telapak', 'w' => 185],
            ['id' => 'handDorsumKiri', 'label' => 'Kiri — Punggung', 'w' => 185],
            ['id' => 'handDorsumKanan', 'label' => 'Kanan — Punggung', 'w' => 185],
        ],
        'Kaki' => [
            ['id' => 'footPalmKanan', 'label' => 'Kanan — Telapak', 'w' => 160],
            ['id' => 'footPalmKiri', 'label' => 'Kiri — Telapak', 'w' => 160],
            ['id' => 'footDorsumKiri', 'label' => 'Kiri — Punggung', 'w' => 160],
            ['id' => 'footDorsumKanan', 'label' => 'Kanan — Punggung', 'w' => 160],
        ],
        'Kepala' => [
            ['id' => 'headFront', 'label' => 'Depan', 'w' => 215],
            ['id' => 'headBack', 'label' => 'Belakang', 'w' => 215],
            ['id' => 'headProfileKiri', 'label' => 'Profil Kiri', 'w' => 215],
            ['id' => 'headProfileKanan', 'label' => 'Profil Kanan', 'w' => 215],
        ],
    ];

    // margin (breathing room) supaya figur tak menempel tepi kotak
    $mx = round($VBW * 0.05, 1);
    $my = round($VBH * 0.03, 1);
    $vw = $VBW + 2 * $mx;
    $vh = $VBH + 2 * $my;
    $r = round($vw * 0.04, 1);
    $fs = round($r * 1.2, 1);

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
                            @php $h = round($p['w'] * $vh / $vw); @endphp
                            <div style="display:inline-block; vertical-align:top; margin:4px 8px; text-align:center">
                                <div class="text-xs font-medium text-muted dark:text-gray-400" style="margin-bottom:2px">
                                    {{ $p['label'] }}</div>
                                <svg viewBox="{{ -$mx }} {{ -$my }} {{ $vw }} {{ $vh }}" width="{{ $p['w'] }}"
                                    height="{{ $h }}" preserveAspectRatio="xMidYMid meet"
                                    class="bg-white border border-hairline rounded-lg {{ $editable ? 'cursor-crosshair' : '' }}"
                                    style="touch-action:none; max-width:100%; height:auto; display:inline-block"
                                    @if ($editable) x-on:click="const _r = $el.getBoundingClientRect(); $wire.{{ $wireAddMark }}('{{ $p['id'] }}', ($event.clientX - _r.left) / _r.width * 100, ($event.clientY - _r.top) / _r.height * 100)" @endif>
                                    @include($figBase . $p['id'])
                                    @php $n = 0; @endphp
                                    @foreach ($marks ?? [] as $m)
                                        @if (($m['view'] ?? '') === $p['id'])
                                            @php
                                                $n++;
                                                $cx = round(-$mx + ($m['x'] / 100) * $vw, 2);
                                                $cy = round(-$my + ($m['y'] / 100) * $vh, 2);
                                            @endphp
                                            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="#dc2626"
                                                stroke="#ffffff" stroke-width="1.5" />
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
