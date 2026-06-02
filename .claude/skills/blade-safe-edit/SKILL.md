---
name: blade-safe-edit
description: Aturan keselamatan saat mengedit file Blade / Volt di repo ini. Baca sebelum melakukan edit bulk atau pakai sed/perl/regex pada *.blade.php — mencegah match melebar yang merusak banyak file. Juga mencakup jebakan compiler Volt.
---

# Blade Safe Edit

File Blade di repo ini besar dan banyak nested tag. Edit ceroboh gampang merusak banyak file sekaligus.

## 1. JANGAN regex multiline untuk Blade
`perl -0` / `sed` multiline rawan match melebar dan merusak banyak file diam-diam.

- Pakai tool **Edit** dengan `old_string` presisi (sertakan konteks unik).
- Untuk perubahan berulang yang identik, pakai `replace_all: true` pada Edit — bukan sed.

## 2. Verifikasi sesudah edit — `php -l` TIDAK cukup
`php -l` lolos walau struktur tag Blade kacau. Selalu cek:

```bash
git diff --stat                 # pastikan jumlah file/baris berubah masuk akal
# hitung balance tag yang diedit, mis. modal/div pembuka vs penutup
grep -c '@if' file.blade.php; grep -c '@endif' file.blade.php
grep -c '<x-modal' file.blade.php; grep -c '</x-modal' file.blade.php
```
Diff-stat yang membengkak = tanda match melebar → batalkan.

## 3. Volt: hindari kata "use" di komentar PHP
Compiler Volt salah-strip komentar `//` bila ada substring `re-use` / `reuse` — sisanya terbaca sebagai statement `use` → **ParseError**.

```php
// SALAH di blok <?php Volt:  // re-use komponen ini
// BENAR: tulis ulang tanpa "use", mis. "pakai ulang komponen ini"
```

## 4. Pola UI sudah terdokumentasi — jangan reinvent
Sebelum bikin komponen, cek `docs/` (lihat skill `ui-pattern-docs`): tombol standar, now-button, print PDF/TTD, page-frame, dirty-modal, stable-lookup, tinymce. Ikuti pola yang ada agar konsisten.
