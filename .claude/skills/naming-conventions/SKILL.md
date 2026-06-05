---
name: naming-conventions
description: Standar penamaan variable/method & aturan import (use vs FQCN) di repo ini. Baca sebelum menulis kode PHP/Livewire/Volt baru — terutama saat menamai variable untuk konsep domain (risiko jatuh, dll.) atau menambahkan pemakaian class seperti Carbon di file Volt.
---

# Naming Conventions & Imports

## 1. Singkatan modul sudah "dipesan" — jangan dipakai untuk makna lain

Di repo ini singkatan berikut SELALU berarti modul/unit, bukan yang lain:

| Singkatan | Artinya | BUKAN |
|---|---|---|
| `rj` / `$rj` | Rawat Jalan | risiko jatuh |
| `ri` / `$ri` | Rawat Inap | — |
| `ugd` | Unit Gawat Darurat | — |
| `rm` | Rekam Medis / No. RM | — |

Contoh kasus nyata: variable risiko jatuh sempat dinamai `$rjList`, `$rjKategori` — di file RJ, `$rj` adalah data rawat jalan → tabrakan makna, ditolak user.

**Aturan:** konsep domain ditulis LENGKAP, camelCase bahasa Indonesia, ikut idiom field JSON-nya:
`$resikoJatuhTerakhir`, `hitungResikoJatuhTerakhir()`, `$kategoriResiko`, `$tglPenilaian`.
Variable lokal pendek boleh asal tidak ambigu: `$entri`, `$terakhir`, `$maxTimestamp`.

## 2. `use` import vs FQCN di file Volt

File Volt SFC punya 2 zona PHP yang **dikompilasi terpisah**:

1. **Blok `<?php ... ?>` atas** (class component) → import normal berlaku.
   Tulis `use Carbon\Carbon;` di atas dan pakai `Carbon::` — JANGAN `\Carbon\Carbon::` inline di zona ini.
2. **`@php ... @endphp` di template** → import dari blok atas TIDAK menjangkau sini;
   FQCN `\Carbon\Carbon::` memang diperlukan kalau terpaksa.

**Aturan:** logika non-trivial (loop, parsing tanggal, agregasi) JANGAN ditaruh di `@php`
template — pindahkan ke method class (private + public property hasil). Template `@php`
hanya untuk mapping display ringan. Dengan begitu FQCN nyaris tidak pernah dibutuhkan.

## 3. Konsistensi gaya yang sudah jalan

- Property/method Livewire: camelCase bahasa Indonesia sesuai domain (`$dataDaftarRi`, `openDisplay`, `hitungResikoJatuhTerakhir`).
- Key JSON EMR: ikuti key yang sudah ada di `datadaftar*_json` (`resikoJatuh`, `kategoriResiko`) — jangan menerjemahkan/menyingkat ulang.
- Kolom Oracle: snake_case lowercase di query (`bed_no`, `room_name`) — lihat skill `oracle-quirks` untuk jebakan mixed-case.
- Komentar di blok `<?php` Volt: hindari substring `reuse`/`re-use` (lihat skill `blade-safe-edit` §3).
