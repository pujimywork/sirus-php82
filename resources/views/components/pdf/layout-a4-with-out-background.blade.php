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
