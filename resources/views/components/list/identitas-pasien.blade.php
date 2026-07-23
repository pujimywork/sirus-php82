@props([
    'regNo' => null,      // No. RM
    'nama' => null,       // Nama pasien
    'sex' => null,        // 'L' | 'P' | lainnya
    'tglLahir' => null,   // tanggal lahir (dd/mm/yyyy)
    'umur' => null,       // OVERRIDE opsional; kosongkan agar dihitung dari tglLahir
    'alamat' => null,     // alamat pasien
    'collapseUmur' => false, // true = baris tgl-lahir/umur ikut toggle Alpine `expanded` (mis. list RJ/UGD)
])

{{--
    Identitas pasien di kolom PASIEN list transaksi (LAYAR — bukan cetak).
    Acuan tampilan: transaksi/rj/pelayanan-rj. Urutan baku:
      No RM → Nama / (gender) → tgl lahir (umur) → alamat.
    - Umur SELALU dihitung ulang dari `tglLahir` (birth_date) di sini — SATU sumber,
      SATU format "X Thn Y Bln Z Hr", dijamin fresh (bukan kolom snapshot thn/bln/hari).
      Oper `:umur` HANYA bila perlu override format khusus.
    - collapseUmur=true: baris tgl-lahir/umur dibungkus `x-show="expanded" x-collapse`
      (butuh Alpine `expanded` di scope induk — dipakai di list yg punya toggle detail).
    - $slot (opsional): baris tambahan spesifik halaman (mis. "Masuk: ..." di kasir),
      dirender setelah alamat.
    Beda dari cetak PDF: lihat <x-pdf.identitas-pasien>.
--}}
@php
    // Umur dihitung dari tglLahir (format d/m/Y). Override lewat :umur bila diisi.
    $umurTampil = filled($umur) && $umur !== '-' ? $umur : null;
    if (!$umurTampil && filled($tglLahir) && $tglLahir !== '-') {
        try {
            $selisih = \Carbon\Carbon::createFromFormat('d/m/Y', $tglLahir)->diff(\Carbon\Carbon::now(config('app.timezone')));
            $umurTampil = "{$selisih->y} Thn {$selisih->m} Bln {$selisih->d} Hr";
        } catch (\Throwable $e) {
            $umurTampil = null;
        }
    }
@endphp
<div {{ $attributes->merge(['class' => 'space-y-0 leading-tight']) }}>
    <div class="text-base font-medium text-body dark:text-gray-300">
        {{ $regNo ?? '-' }}
    </div>
    <div class="inline-flex items-center gap-1.5 text-lg font-semibold text-brand dark:text-white">
        <span>{{ $nama ?? '-' }}</span>
        {{-- Jenis kelamin sebagai simbol: ♂ biru (L) / ♀ rose (P). title utk aksesibilitas. --}}
        @if ($sex === 'L')
            <svg class="w-4 h-4 shrink-0 text-blue-500 dark:text-blue-400" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" role="img" aria-label="Laki-Laki">
                <title>Laki-Laki</title>
                <circle cx="9.5" cy="14.5" r="5.5" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 9l5-5m0 0h-5m5 0v5" />
            </svg>
        @elseif ($sex === 'P')
            <svg class="w-4 h-4 shrink-0 text-rose-500 dark:text-rose-400" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" role="img" aria-label="Perempuan">
                <title>Perempuan</title>
                <circle cx="12" cy="8.5" r="5.5" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 14v7m-3.5-3.5h7" />
            </svg>
        @endif
    </div>
    <div @class(['text-sm text-body dark:text-gray-400']) @if ($collapseUmur) x-show="expanded" x-collapse @endif>
        {{ $tglLahir ?? '-' }}
        @if ($umurTampil)
            <span class="text-muted">({{ $umurTampil }})</span>
        @endif
    </div>
    <div class="text-sm text-muted dark:text-gray-400">
        {{ $alamat ?? '-' }}
    </div>
    {{ $slot }}
</div>
