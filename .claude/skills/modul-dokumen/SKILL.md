---
name: modul-dokumen
description: Pola membuat/mem-port modul dokumen bertanda tangan (consent, surat keterangan, laporan, Pengkajian Akhir Hayat) di EMR — kartu+tombol→modal, siklus Draft→TTD→Kunci→Lihat/Cetak, multi-entri, clause-versioning, PLUS wajib mendaftarkan viewer di display Rekam Medis dan (bila lintas jalur) porting RI⇄UGD⇄RJ. WAJIB dibaca sebelum membuat form dokumen baru, memasangnya di jalur lain, atau menambah viewer rekam-medisnya. Beda dari emr-multi-entry-document (CPPT/SBAR): di sini entri ditandatangani pasien/keluarga/saksi/petugas lalu terkunci & dicetak.
---

# Modul Dokumen (formulir bertanda tangan, multi-entri)

Acuan lengkap — **baca sebelum implementasi**:
- `docs/modul-dokumen-ri-pattern.md` — struktur file, siklus entri, validasi, TTD, buka-kunci, port jalur, viewer.
- `docs/dokumen-view-pattern.md` — viewer read-only (Lihat = render blade cetak ke iframe).
- `docs/clause-versioning.md` — teks legal WAJIB versioning (baca sebelum mengubah redaksi klausul).

Contoh kanonik: **Inform Consent RI** (paling lengkap) & **Pengkajian Akhir Hayat** (terbaru,
sudah di RI + UGD, contoh cetak payload bespoke). Beda dari skill `emr-multi-entry-document`
(CPPT/SBAR: banyak PPA + review DPJP) — di sini entri **di-TTD pasien/keluarga/saksi/petugas → terkunci → dicetak**.

## Titik sentuh saat menambah SATU dokumen di SATU jalur

1. `…/modul-dokumen/<dok>/rm-<dok>-actions.blade.php` — komponen Volt (form + siklus + cetak).
2. `…/components/modul-dokumen/<jalur>/<dok>/cetak-<dok>-print.blade.php` — cetak PDF.
3. Daftarkan **tab + panel** di `modul-dokumen-<jalur>.blade.php` (`<x-tab>` + `<div x-show>` berisi `<livewire:… :rjNo/riHdrNo :disabled wire:key>`).
4. **Viewer rekam medis** — `…/rekam-medis/<jalur>/dokumen-view/<dok>-view-<jalur>.blade.php` **dan** daftarkan di `cetak-rekam-medis-open.blade.php`. **Belum selesai tanpa langkah ini.**

## Aturan keras (paling sering keliru)

1. **TTD petugas = aksi TERAKHIR yang sekaligus MENGUNCI** (`ttdPetugas()`/`setDokterPenjelas`). JANGAN sediakan tombol "Simpan & Kunci" terpisah — footer cukup **Simpan Draft**. TTD masuk `rules()` supaya error merah di kolomnya.
2. **Role Buka Kunci & Hapus = SATU SUMBER** `App\Support\ModulDokumenAksiRole` (konstanta `BUKA_KUNCI` & `HAPUS`; saat ini triad `Admin | Manager Umum | Manager Medis`). Dipakai via **Gate** `dokumen.bukaKunci` & `dokumen.hapus` (didaftarkan di `AppServiceProvider::boot()`). **JANGAN** tulis `@hasanyrole('Admin|Manager Umum|Manager Medis')`/`hasAnyRole([...])` literal — pakai `@can('dokumen.bukaKunci')`/`@can('dokumen.hapus')` (blade) & `auth()->user()?->can('dokumen.hapus')` (server, guard DUA lapis). Menambah/mengurangi role = ubah 1 file itu, otomatis berlaku di semua modul RI/UGD/RJ. Buka kunci mencabut `finalized` + **TTD petugas saja** (TTD pasien/keluarga & saksi DIPERTAHANKAN); wajib `appendAdminLog<Jalur>(…, 'MR')` menyebut pelakunya.
3. **Tulis data** SELALU via `DB::transaction` + `lock<Jalur>Row` + `updateJson<Jalur>` + audit MR. Muat entri lama dgn `array_replace_recursive(defaultForm(), $entri['form'])` (record legacy aman).
4. **Teks klausul = versioning** (`App\Support\*Clause`, snapshot versi saat TTD). **Pre-fill wajib di-sync di save()** — prop yang tak diedit user tak otomatis masuk array form (hilang di cetak).
5. **Peta label cetak** (skala/checklist/prognosis) → `App\Support\<Dok>Options::labels()` = satu sumber, dipakai form + semua viewer. Jangan diduplikasi.
6. **Baris aksi tabel entri seragam** (4 tombol): **Lihat** = `viewEntry('{{ $rowKey }}')` `<x-secondary-button>`; **Cetak** = `cetak('{{ $rowKey }}')` `<x-secondary-button>`/`<x-info-button>` dgn spinner swap (`<span wire:loading.remove …>` ikon+"Cetak" / `<span wire:loading …><x-loading/> Mencetak...`); **Buka Kunci** = `<x-confirm-button action="bukaKunci('{{ $rowKey }}')">` di dalam `@can('dokumen.bukaKunci') … @endcan`; **Hapus** = `<x-outline-button wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm>` di dalam `@can('dokumen.hapus') … @endcan` + guard `if (!auth()->user()?->can('dokumen.hapus')) { toast; return; }` sebagai statement pertama di method hapus. Varian fungsional Cetak (`cetakSemua`/`cetakLembar`/`cetakFormA-B`) boleh tetap. Doc: `docs/modul-dokumen-ri-pattern.md`.

## Port ke jalur lain (RI ⇄ UGD ⇄ RJ)

Salin actions + cetak, ganti token **per-string** (bukan `RI→UGD` global). Tabel lengkap di
`docs/modul-dokumen-ri-pattern.md §9`. Ringkas: trait `EmrRITrait→EmrUGDTrait`; prop
`?string $riHdrNo → ?int $rjNo`; `dataDaftarRi→dataDaftarUGD`; `findDataRI/checkEmrRIStatus/updateJsonRI/appendAdminLogRI/lockRIRow → …UGD`;
key JSON `pengkajian<Dok>RI→…UGD`; modal/area `-ri→-ugd`; `display-pasien-ri→display-pasien-ugd`.
Folder/file UGD/RJ **buang sufiks** `-ri`, tapi modal-name/renderArea/nama PDF **tetap** `-ugd`/`-rj`.

## Viewer rekam-medis: payload seragam vs bespoke

- **Seragam** (dataRi/form/ttd): pakai `DokumenViewSupportTrait::previewDokumenRi()/streamCetakDokumenRi()` langsung.
- **Bespoke** (cetak butuh `entry`+`opsiLabel`+`clause`, mis. Akhir Hayat): viewer **self-contained** — `dvPasien/dvTtdPath/dvIdentitasRs/renderDokumenPreview` + `buildData()` yang meniru `cetak()`; `opsiLabel` dari `App\Support\<Dok>Options::labels()`.

## Verifikasi (WAJIB sebelum lapor selesai)

- **`php artisan view:cache`** → EXIT 0 (pipeline Blade asli), lalu `php artisan view:clear`.
  Jangan andalkan `Blade::compileString` untuk file host rekam-medis — banyak yang tak
  standalone-compilable (bandingkan dgn `git HEAD`: gagal identik = pre-existing).
- `grep` tidak ada token jalur asal yang nyasar di file hasil port.
- Ikuti skill `blade-safe-edit` saat sed/regex pada `*.blade.php` (edit fresh copy, token presisi).
