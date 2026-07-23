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

## 7. Enter-chain antar field (pola e-resep) — STANDAR untuk entry multi-field/multi-baris
Form entry cepat (e-resep, pihak akses info medis general consent, dan entry berbaris
lainnya) pakai Enter untuk pindah field & tambah baris. Aturan:

- **Field yang SUDAH dirender** → `x-ref` + `$refs`, di dalam `@foreach` suffix index
  (acuan: e-resep racikan `$refs.signaX{{ $key }}`):

```html
<x-text-input wire:model.live.debounce.500ms="rows.{{ $i }}.nama"
    x-on:keydown.enter.prevent="$refs.hub{{ $i }}.focus()" />
<x-text-input x-ref="hub{{ $i }}" wire:model.live.debounce.500ms="rows.{{ $i }}.hubungan"
    x-on:keydown.enter.prevent="$refs.hp{{ $i }}.focus()" />
```

- **Field terakhir → tambah baris baru**: elemen baris baru BELUM ada di DOM saat Enter
  ditekan, `$refs` tidak bisa — wajib `id` unik + `getElementById` + `setTimeout` pasca-morph:

```html
<x-text-input x-ref="hp{{ $i }}" wire:model.live.debounce.500ms="rows.{{ $i }}.noHp"
    x-on:keydown.enter.prevent="$el.blur(); $wire.addRow().then(() =>
        setTimeout(() => document.getElementById('row-nama-{{ $i + 1 }}')?.focus(), 100))" />
```
  (field pertama tiap baris diberi `id="row-nama-{{ $i }}"` sebagai target focus;
  `$el.blur()` dulu sesuai pola #3; `?.` aman saat baris mentok limit.)

- `x-init="$nextTick(() => $el.focus())"` hanya untuk auto-focus saat form/elemen pertama
  kali muncul (mis. form e-resep dibuka) — jangan dipasang di baris loop, rebutan focus.

## 8. Search input "mental" (fokus hilang saat ketik) — JANGAN wire:key dinamis
Input search dengan `wire:key` yang berubah tiap render (mis. `wire:key="search-input-{{ now() }}"`)
di-REMOUNT setiap respons Livewire → fokus hilang di tengah ketik. Sama juga untuk
`incrementVersion()` pada wire:key toolbar yang membungkus input search.

```html
<!-- ❌ SALAH — remount tiap render, fokus mental -->
<x-text-input wire:model.live.debounce.300ms="searchKeyword" wire:key="search-input-{{ now() }}" />
<!-- ✅ BENAR — tanpa wire:key (elemen stabil), acuan: master-poli -->
<x-text-input wire:model.live.debounce.300ms="searchKeyword" />
```
Di `updatedSearchKeyword()` cukup `resetPage()` — JANGAN `incrementVersion` area yang memuat input search (acuan: daftar-laborat).

## 9. Persist filter antar tab (wrapper server-mode) — `#[Session]`
Wrapper tab mode Server (`@if ($activeTab==='rj') <livewire:.../> @elseif...`, mis. `/transaksi/apotek`, `/kasir`, `/casemix`) meng-**unmount/remount** komponen anak saat ganti tab → `mount()` jalan lagi → filter (mis. `filterTanggal = today`) **ter-reset**.

Fix: `#[Session(key: '<komponen>-<properti>')]` di TIAP properti filter/search/itemsPerPage + guard `mount()`:
```php
use Livewire\Attributes\Session;
#[Session(key: 'antrian-apotek-rj-filterTanggal')]
public string $filterTanggal = '';
public function mount(): void {
    $this->filterTanggal = $this->filterTanggal ?: Carbon::now()->format('d/m/Y'); // guard: default hanya bila kosong
}
```
- Key WAJIB namespaced per komponen (bentrok = filter bocor antar komponen).
- JANGAN Session-kan `autoRefresh`/`renderVersions`/cache data. JANGAN guard `resetFilters()` (Reset harus memaksa default).
- JANGAN ganti tab jadi Alpine `x-show` untuk mengatasinya — komponen `wire:poll` akan polling semua tab sekaligus.
- Detail + contoh lengkap: `docs/tabs-pattern.md` §"Persist state anak saat ganti tab".
