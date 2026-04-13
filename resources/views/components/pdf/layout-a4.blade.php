{{-- resources/views/components/pdf/layout-a4.blade.php --}}
@props([
    'title' => null,
    'showGaris' => false,
    'showWatermark' => false,
    'patientData' => null,
])

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Dokumen' }}</title>
    @php
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $pdfCss = $manifest['resources/css/app.css']['file'] ?? null;
    @endphp
    <style>
        @page {
            size: A4;
            margin: 30px 0 20px 0;
        }

        @page :first {
            margin-top: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-size: 11px;
            font-family: sans-serif;
        }

        /* ── BACKGROUND ── */
        .pdf-background {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 0;
            pointer-events: none;
        }

        .ornamen.kiri-atas {
            position: fixed;
            top: -15px;
            left: -15px;
            width: 160px;
            opacity: 1;
        }

        .ornamen.kanan-bawah {
            position: fixed;
            bottom: -20px;
            right: -20px;
            width: 220px;
            transform: rotate(180deg);
            opacity: 1;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 400px;
            opacity: 0.05;
        }

        /* ── CONTENT ── */
        .pdf-content {
            position: relative;
            z-index: 10;
            padding: 30px 40px 30px 40px;
        }

        {!! $pdfCss ? file_get_contents(public_path('build/' . $pdfCss)) : '' !!}
    </style>
</head>

<body>

    {{-- BACKGROUND LAYER --}}
    <div class="pdf-background">
        <img src="{{ public_path('images/bulansabit.png') }}" class="ornamen kiri-atas" alt="">
        <img src="{{ public_path('images/bulansabit.png') }}" class="ornamen kanan-bawah" alt="">
        @if ($showWatermark)
            <img src="{{ public_path('images/Logo Persegi.png') }}" class="watermark" alt="">
        @endif
    </div>

    {{-- CONTENT LAYER --}}
    <div class="pdf-content">

        {{-- KOP SURAT — bisa sejajar dengan data pasien --}}
        @if ($patientData ?? false)
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="50%" style="vertical-align: bottom;">
                        {{ $patientData }}
                    </td>
                    <td width="50%" style="vertical-align: bottom; padding-left: 8px;">
                        <x-logo.identitas :showGaris="false" />
                    </td>
                </tr>
            </table>
            @if ($showGaris)
                <hr style="border: none; border-top: 2px solid #000; margin: 8px 0;">
            @endif
        @else
            <x-logo.identitas :showGaris="$showGaris" />
        @endif

        {{-- Judul dokumen --}}
        @if ($title)
            <div
                style="margin-top:16px; margin-bottom:12px; font-size:13px; font-weight:bold; text-align:center; text-decoration:underline;">
                {{ $title }}
            </div>
        @endif

        {{-- Konten utama --}}
        {{ $slot }}

    </div>

</body>

</html>
