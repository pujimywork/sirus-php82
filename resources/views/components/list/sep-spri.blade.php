@props([
    'sep' => null,   // No. SEP (BPJS)
    'spri' => null,  // No. SPRI (Surat Perintah Rawat Inap) — hanya relevan bila ada
])

{{--
    Penanda No. SEP & SPRI standar untuk semua list transaksi (Daftar/Kasir/Bulanan RJ/UGD/RI).
    - SEP  : mono, kecil, abu  (text-muted / dark:gray-300)
    - SPRI : mono, kecil, ungu (purple-600 / dark:purple-400)
    Keduanya tampil hanya bila ada nilainya; '-' diperlakukan sebagai kosong.
    Sumber standar: transaksi/ri/daftar-ri.
--}}
@php
    $sepVal = filled($sep) && $sep !== '-' ? $sep : null;
    $spriVal = filled($spri) && $spri !== '-' ? $spri : null;
@endphp

@if ($sepVal)
    <div class="font-mono text-xs text-muted dark:text-gray-300">SEP: {{ $sepVal }}</div>
@endif
@if ($spriVal)
    <div class="font-mono text-xs text-purple-600 dark:text-purple-400">SPRI: {{ $spriVal }}</div>
@endif
