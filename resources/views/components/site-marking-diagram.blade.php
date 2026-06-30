{{--
    <x-site-marking-diagram> — Diagram Penanda Lokasi Operasi (reusable).

    Figur SVG vektor (hasil trace "Formulir Penandaan Area Operasi") ada di
    resources/views/components/site-marking/figs/<id>.blade.php.
    Komponen ini portable: salin folder components/site-marking* ke aplikasi lain.

    Props:
      :marks       array   — [['view'=>panelId,'x'=>%,'y'=>%], ...] (x/y persen 0..100 relatif panel)
      :editable    bool    — true: panel bisa diklik (butuh konteks Livewire); false: statis (cetak/preview)
      wire-add-mark string — nama method Livewire dipanggil saat klik: method($view,$x,$y). default 'addMark'

    Contoh:
      <x-site-marking-diagram :marks="$marks" :editable="!$isFormLocked" wire-add-mark="addMark" />
      <x-site-marking-diagram :marks="$entry['marks'] ?? []" :editable="false" />  {{-- cetak --}}
--}}
@props([
    'marks' => [],
    'editable' => false,
    'wireAddMark' => 'addMark',
])

@php
    $figBase = 'components.site-marking.figs.';

    // id => [label, vbw, vbh, w(px tampil)]. vbw/vbh = ukuran intrinsik figur (px trace).
    $groups = [
        'Tubuh' => [
            ['id' => 'priaFront', 'label' => 'Pria — Depan', 'vbw' => 410, 'vbh' => 1088, 'w' => 150],
            ['id' => 'priaBack', 'label' => 'Pria — Belakang', 'vbw' => 420, 'vbh' => 1088, 'w' => 150],
            ['id' => 'wanitaFront', 'label' => 'Wanita — Depan', 'vbw' => 420, 'vbh' => 1088, 'w' => 155],
            ['id' => 'wanitaBack', 'label' => 'Wanita — Belakang', 'vbw' => 428, 'vbh' => 1088, 'w' => 160],
        ],
        'Tangan' => [
            ['id' => 'handPalmKiri', 'label' => 'Kiri — Telapak', 'vbw' => 224, 'vbh' => 300, 'w' => 135],
            ['id' => 'handPalmKanan', 'label' => 'Kanan — Telapak', 'vbw' => 224, 'vbh' => 300, 'w' => 135],
            ['id' => 'handDorsumKiri', 'label' => 'Kiri — Punggung', 'vbw' => 224, 'vbh' => 300, 'w' => 130],
            ['id' => 'handDorsumKanan', 'label' => 'Kanan — Punggung', 'vbw' => 240, 'vbh' => 300, 'w' => 130],
        ],
        'Kaki' => [
            ['id' => 'footPalmKanan', 'label' => 'Kanan — Telapak', 'vbw' => 176, 'vbh' => 300, 'w' => 120],
            ['id' => 'footPalmKiri', 'label' => 'Kiri — Telapak', 'vbw' => 184, 'vbh' => 300, 'w' => 122],
            ['id' => 'footDorsumKiri', 'label' => 'Kiri — Punggung', 'vbw' => 184, 'vbh' => 300, 'w' => 122],
            ['id' => 'footDorsumKanan', 'label' => 'Kanan — Punggung', 'vbw' => 184, 'vbh' => 300, 'w' => 122],
        ],
        'Kepala' => [
            ['id' => 'headFront', 'label' => 'Depan', 'vbw' => 448, 'vbh' => 436, 'w' => 190],
            ['id' => 'headBack', 'label' => 'Belakang', 'vbw' => 450, 'vbh' => 436, 'w' => 188],
            ['id' => 'headProfileKiri', 'label' => 'Profil Kiri', 'vbw' => 386, 'vbh' => 436, 'w' => 182],
            ['id' => 'headProfileKanan', 'label' => 'Profil Kanan', 'vbw' => 376, 'vbh' => 436, 'w' => 178],
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
                                // margin (breathing room) supaya figur tak menempel tepi kotak
                                $mx = round($p['vbw'] * 0.06, 1);
                                $my = round($p['vbh'] * 0.03, 1);
                                $vw = $p['vbw'] + 2 * $mx;
                                $vh = $p['vbh'] + 2 * $my;
                                $h = round($p['w'] * $vh / $vw);
                                $r = round($vw * 0.038, 1);
                                $fs = round($r * 1.25, 1);
                            @endphp
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
