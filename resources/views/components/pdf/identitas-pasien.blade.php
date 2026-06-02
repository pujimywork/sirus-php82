{{-- resources/views/components/pdf/identitas-pasien.blade.php

    Blok identitas pasien STANDAR untuk semua cetakan PDF.
    Urutan & label baku:
      No. Rekam Medis · Nama Pasien · Jenis Kelamin · Tempat, Tgl. Lahir · Alamat · Tanggal

    Pakai:
      <x-pdf.identitas-pasien
          :rm="..." :nama="..." :jenisKelamin="..."
          :tempatLahir="..." :tglLahir="..." :umur="..."
          :alamat="..." :tanggal="..." />

    Field kosong tampil '-'. Baris "Tanggal" hanya muncul bila :tanggal diisi.
    Field konteks tambahan (Ruang/Kelas, Tgl Masuk, No SEP, dll.) dioper lewat
    default slot sebagai <tr>...</tr> dan akan muncul DI BAWAH blok standar.
--}}
@props([
    'rm' => null,
    'nama' => null,
    'jenisKelamin' => null,
    'tempatLahir' => null,
    'tglLahir' => null,
    'umur' => null,
    'alamat' => null,
    'tanggal' => null,
])
@php
    $dash = fn($v) => filled($v) ? $v : '-';

    // Gabungan "Tempat, Tgl. Lahir (umur)" — toleran bila ada bagian yang kosong.
    $ttl = trim((filled($tempatLahir) ? $tempatLahir : '') . (filled($tempatLahir) && filled($tglLahir) ? ', ' : '') . (filled($tglLahir) ? $tglLahir : ''));
    if (filled($umur)) {
        $ttl = trim($ttl . ' (' . $umur . ')');
    }
    if ($ttl === '') {
        $ttl = '-';
    }
@endphp
<table cellpadding="0" cellspacing="0" {{ $attributes }}>
    <tr>
        <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">No. Rekam Medis</td>
        <td class="py-0.5 text-[11px] px-1">:</td>
        <td class="py-0.5 text-[11px] font-bold">{{ $dash($rm) }}</td>
    </tr>
    <tr>
        <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Nama Pasien</td>
        <td class="py-0.5 text-[11px] px-1">:</td>
        <td class="py-0.5 text-[11px] font-bold">{{ filled($nama) ? strtoupper($nama) : '-' }}</td>
    </tr>
    <tr>
        <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Jenis Kelamin</td>
        <td class="py-0.5 text-[11px] px-1">:</td>
        <td class="py-0.5 text-[11px]">{{ $dash($jenisKelamin) }}</td>
    </tr>
    <tr>
        <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tempat, Tgl. Lahir</td>
        <td class="py-0.5 text-[11px] px-1">:</td>
        <td class="py-0.5 text-[11px]">{{ $ttl }}</td>
    </tr>
    <tr>
        <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap align-top">Alamat</td>
        <td class="py-0.5 text-[11px] px-1 align-top">:</td>
        <td class="py-0.5 text-[11px]">{{ $dash($alamat) }}</td>
    </tr>
    @if (filled($tanggal))
        <tr>
            <td class="py-0.5 text-[11px] text-gray-500 whitespace-nowrap">Tanggal</td>
            <td class="py-0.5 text-[11px] px-1">:</td>
            <td class="py-0.5 text-[11px]">{{ $tanggal }}</td>
        </tr>
    @endif
    {{ $slot }}
</table>
