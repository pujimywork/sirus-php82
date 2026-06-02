---
name: livewire-input-patterns
description: Pola input Livewire/Alpine yang sudah teruji di EMR repo ini — mencegah digit hilang, race condition Enter, dan masalah sinkronisasi blur. Baca saat menambah/men-debug input numerik EMR, aksi Enter→$wire, atau komponen x-text-input-number / x-now-button.
---

# Livewire / Alpine Input Patterns (EMR)

## 1. Input numerik auto-calc → pakai `wire:model.blur`
`wire:model.live` / `.live.debounce.500ms` pada input numerik EMR rawan **digit hilang** saat user mengetik cepat. Untuk field auto-calc (BB, TB, IMT, LK, LILA) pakai `wire:model.blur`.

## 2. x-text-input-number sync via $wire.set di blur
Komponen `x-text-input-number` menyinkron nilai lewat `$wire.set` saat blur. Maka aksi Enter→insert harus mem-blur dulu agar nilai kekirim:

```html
@keydown.enter.prevent="$el.blur(); $wire.simpan()"
```

## 3. Enter→$wire race condition (double-fire)
`@keyup.enter="$wire.X()"` bisa double-fire saat user pencet Enter 2x cepat. Pola aman:

```html
@keydown.enter.prevent="$el.blur(); $wire.X().then(() => $el.focus())"
```
`keydown.enter.prevent` + `$el.blur()` + `.then()` refocus.

## 4. x-now-button untuk set tanggal/waktu
Tombol set-waktu standar = komponen `x-now-button` (icon jam, pass-through atribut). Pakai untuk semua `setTgl` / `setWaktu`. Pengecualian: "Hari Ini" pada tanggal pulang dikecualikan dari pola ini.

## 5. Validasi → toast + x-input-error
Pakai trait `WithValidationToast` untuk menampilkan error validasi sebagai toast, plus `x-input-error` di view dokter/perawat EMR. (RJ/UGD sudah pakai pola ini.)

## 6. Stable lookup list (dokterList dkawan-kawan)
Lookup list (mis. `dokterList`) HANYA boleh depend pada tanggal — decouple dari `filterStatus` / `filterKlaim` agar tidak re-query tiap filter berubah. Detail di `docs/stable-lookup-list-pattern.md`.
