{{--
    <x-site-marking-diagram> — Diagram Penanda Lokasi Operasi (reusable).

    16 figur SVG vektor "Formulir Penandaan Area Operasi", 1 file/figur di
    resources/views/components/site-marking/figs/<id>.blade.php (kanvas 210x297).
    Tiap panel pakai viewBox = bounding-box figur (+pad) → figur fit penuh.
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

    // id => [label, vx,vy,vw,vh (viewBox bbox figur), w(px tampil)]
    $groups = [
        'Tubuh' => [
            ['id' => 'priaFront', 'label' => 'Pria — Depan', 'vx' => 44, 'vy' => 53, 'vw' => 112, 'vh' => 250, 'w' => 160],
            ['id' => 'priaBack', 'label' => 'Pria — Belakang', 'vx' => 45, 'vy' => 53, 'vw' => 112, 'vh' => 250, 'w' => 160],
            ['id' => 'wanitaFront', 'label' => 'Wanita — Depan', 'vx' => 47, 'vy' => 53, 'vw' => 112, 'vh' => 250, 'w' => 160],
            ['id' => 'wanitaBack', 'label' => 'Wanita — Belakang', 'vx' => 45, 'vy' => 53, 'vw' => 112, 'vh' => 250, 'w' => 160],
        ],
        'Tangan' => [
            ['id' => 'handPalmKiri', 'label' => 'Kiri — Telapak', 'vx' => 34, 'vy' => 53, 'vw' => 112, 'vh' => 159, 'w' => 180],
            ['id' => 'handPalmKanan', 'label' => 'Kanan — Telapak', 'vx' => 61, 'vy' => 53, 'vw' => 112, 'vh' => 157, 'w' => 180],
            ['id' => 'handDorsumKiri', 'label' => 'Kiri — Punggung', 'vx' => 42, 'vy' => 52, 'vw' => 112, 'vh' => 141, 'w' => 180],
            ['id' => 'handDorsumKanan', 'label' => 'Kanan — Punggung', 'vx' => 39, 'vy' => 54, 'vw' => 112, 'vh' => 147, 'w' => 180],
        ],
        'Kaki' => [
            ['id' => 'footPalmKanan', 'label' => 'Kanan — Telapak', 'vx' => 29, 'vy' => 54, 'vw' => 112, 'vh' => 210, 'w' => 150],
            ['id' => 'footPalmKiri', 'label' => 'Kiri — Telapak', 'vx' => 58, 'vy' => 54, 'vw' => 112, 'vh' => 212, 'w' => 150],
            ['id' => 'footDorsumKiri', 'label' => 'Kiri — Punggung', 'vx' => -3, 'vy' => 54, 'vw' => 112, 'vh' => 213, 'w' => 150],
            ['id' => 'footDorsumKanan', 'label' => 'Kanan — Punggung', 'vx' => 94, 'vy' => 54, 'vw' => 112, 'vh' => 213, 'w' => 150],
        ],
        'Kepala' => [
            ['id' => 'headFront', 'label' => 'Depan', 'vx' => 40, 'vy' => 54, 'vw' => 112, 'vh' => 110, 'w' => 200],
            ['id' => 'headBack', 'label' => 'Belakang', 'vx' => 34, 'vy' => 54, 'vw' => 112, 'vh' => 115, 'w' => 200],
            ['id' => 'headProfileKiri', 'label' => 'Profil Kiri', 'vx' => 46, 'vy' => 54, 'vw' => 112, 'vh' => 150, 'w' => 185],
            ['id' => 'headProfileKanan', 'label' => 'Profil Kanan', 'vx' => 36, 'vy' => 53, 'vw' => 112, 'vh' => 163, 'w' => 185],
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
                                $r = round($p['vw'] * 0.045, 1);
                                $fs = round($r * 1.2, 1);
                            @endphp
                            <div style="display:inline-block; vertical-align:top; margin:4px 8px; text-align:center">
                                <div class="text-xs font-medium text-muted dark:text-gray-400" style="margin-bottom:2px">
                                    {{ $p['label'] }}</div>
                                <svg viewBox="{{ $p['vx'] }} {{ $p['vy'] }} {{ $p['vw'] }} {{ $p['vh'] }}"
                                    width="{{ $p['w'] }}" height="{{ $h }}" preserveAspectRatio="xMidYMid meet"
                                    class="bg-white border border-hairline rounded-lg {{ $editable ? 'cursor-crosshair' : '' }}"
                                    style="touch-action:none; max-width:100%; height:auto; display:inline-block"
                                    @if ($editable) x-on:click="const _r = $el.getBoundingClientRect(); $wire.{{ $wireAddMark }}('{{ $p['id'] }}', ($event.clientX - _r.left) / _r.width * 100, ($event.clientY - _r.top) / _r.height * 100)" @endif>
                                    @include($figBase . $p['id'])
                                    @php $n = 0; @endphp
                                    @foreach ($marks ?? [] as $m)
                                        @if (($m['view'] ?? '') === $p['id'])
                                            @php
                                                $n++;
                                                $cx = round($p['vx'] + ($m['x'] / 100) * $p['vw'], 2);
                                                $cy = round($p['vy'] + ($m['y'] / 100) * $p['vh'], 2);
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
