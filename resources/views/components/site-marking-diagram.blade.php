{{--
    <x-site-marking-diagram> — Diagram Penanda Lokasi Operasi (reusable).

    16 figur SVG vektor "Formulir Penandaan Area Operasi", 1 file/figur di
    resources/views/components/site-marking/figs/<id>.blade.php (kanvas 210x297).
    Tiap panel: viewBox = bounding-box figur (vx,vy,vw,vh) → figur fit penuh.
    Tinggi tampil seragam per-grup (lebar dihitung dari tinggi & aspek figur).
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

    // grup => [tinggi tampil px, panel[id,label,vx,vy,vw,vh]]
    $groups = [
        'Tubuh' => ['h' => 360, 'panels' => [
            ['id' => 'priaFront', 'label' => 'Pria — Depan', 'vx' => 44, 'vy' => 53, 'vw' => 112, 'vh' => 258],
            ['id' => 'priaBack', 'label' => 'Pria — Belakang', 'vx' => 45, 'vy' => 53, 'vw' => 112, 'vh' => 274],
            ['id' => 'wanitaFront', 'label' => 'Wanita — Depan', 'vx' => 47, 'vy' => 53, 'vw' => 112, 'vh' => 273],
            ['id' => 'wanitaBack', 'label' => 'Wanita — Belakang', 'vx' => 45, 'vy' => 53, 'vw' => 112, 'vh' => 288],
        ]],
        'Tangan' => ['h' => 210, 'panels' => [
            ['id' => 'handPalmKiri', 'label' => 'Kiri — Telapak', 'vx' => 34, 'vy' => 53, 'vw' => 112, 'vh' => 159],
            ['id' => 'handPalmKanan', 'label' => 'Kanan — Telapak', 'vx' => 61, 'vy' => 53, 'vw' => 112, 'vh' => 157],
            ['id' => 'handDorsumKiri', 'label' => 'Kiri — Punggung', 'vx' => 42, 'vy' => 52, 'vw' => 112, 'vh' => 141],
            ['id' => 'handDorsumKanan', 'label' => 'Kanan — Punggung', 'vx' => 39, 'vy' => 54, 'vw' => 112, 'vh' => 147],
        ]],
        'Kaki' => ['h' => 290, 'panels' => [
            ['id' => 'footPalmKanan', 'label' => 'Kanan — Telapak', 'vx' => 29, 'vy' => 54, 'vw' => 112, 'vh' => 210],
            ['id' => 'footPalmKiri', 'label' => 'Kiri — Telapak', 'vx' => 58, 'vy' => 54, 'vw' => 112, 'vh' => 212],
            ['id' => 'footDorsumKiri', 'label' => 'Kiri — Punggung', 'vx' => -3, 'vy' => 54, 'vw' => 112, 'vh' => 213],
            ['id' => 'footDorsumKanan', 'label' => 'Kanan — Punggung', 'vx' => 94, 'vy' => 54, 'vw' => 112, 'vh' => 213],
        ]],
        'Kepala' => ['h' => 190, 'panels' => [
            ['id' => 'headFront', 'label' => 'Depan', 'vx' => 40, 'vy' => 54, 'vw' => 112, 'vh' => 110],
            ['id' => 'headBack', 'label' => 'Belakang', 'vx' => 34, 'vy' => 54, 'vw' => 112, 'vh' => 115],
            ['id' => 'headProfileKiri', 'label' => 'Profil Kiri', 'vx' => 46, 'vy' => 54, 'vw' => 112, 'vh' => 150],
            ['id' => 'headProfileKanan', 'label' => 'Profil Kanan', 'vx' => 36, 'vy' => 53, 'vw' => 112, 'vh' => 163],
        ]],
    ];

    $countByView = collect($marks ?? [])->groupBy('view')->map->count();
@endphp

<div {{ $attributes->class('w-full overflow-x-auto') }}>
    <div class="space-y-4 min-w-fit">
        @foreach ($groups as $groupName => $cfg)
            @php
                $H = $cfg['h'];
                $panels = $editable
                    ? $cfg['panels']
                    : array_values(array_filter($cfg['panels'], fn($p) => ($countByView[$p['id']] ?? 0) > 0));
            @endphp
            @if (count($panels) > 0)
                <div>
                    <p class="mb-1 text-sm font-semibold text-body dark:text-gray-300">{{ $groupName }}</p>
                    <div style="text-align:center">
                        @foreach ($panels as $p)
                            @php
                                $w = round($H * $p['vw'] / $p['vh']);
                                $r = round($p['vw'] * 0.07, 1);
                                $fs = round($r * 1.35, 1);
                            @endphp
                            <div style="display:inline-block; vertical-align:top; margin:4px 8px; text-align:center">
                                <div class="text-xs font-medium text-muted dark:text-gray-400" style="margin-bottom:2px">
                                    {{ $p['label'] }}</div>
                                <svg viewBox="{{ $p['vx'] }} {{ $p['vy'] }} {{ $p['vw'] }} {{ $p['vh'] }}"
                                    width="{{ $w }}" height="{{ $H }}" preserveAspectRatio="xMidYMid meet"
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
