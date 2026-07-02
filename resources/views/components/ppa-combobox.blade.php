@props([
    'wireModel',                                   // contoh: 'newConsent.petugasPemeriksa'
    'disabled' => false,
    'placeholder' => 'Nama PPA — pilih dari daftar atau ketik',
    'maxlength' => 150,
    'inputId' => null,
])

{{--
    Combobox PPA (Profesional Pemberi Asuhan) — pilih dari daftar atau ketik bebas.
    Sumber daftar = tabel `users` (semua profesi: dokter, perawat, bidan, apoteker, gizi jadi satu),
    yaitu akun yang aktif dipakai login. Delegasi ke x-catatan-signa-combobox agar UX & perilaku
    sama persis dengan combobox signa (filter, keyboard nav, tombol clear/chevron).
--}}
@php
    $ppaOptions = \Illuminate\Support\Facades\DB::table('users')
        ->whereRaw('LENGTH(TRIM(myuser_name)) > 0')
        ->orderBy('myuser_name')
        ->pluck('myuser_name')
        ->unique()
        ->values()
        ->all();
@endphp

<x-catatan-signa-combobox :wireModel="$wireModel" :options="$ppaOptions" :disabled="$disabled"
    :placeholder="$placeholder" :maxlength="$maxlength" :inputId="$inputId" {{ $attributes }} />
