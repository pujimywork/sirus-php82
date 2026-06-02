---
name: ui-pattern-docs
description: Indeks pola UI/komponen terdokumentasi di folder docs/. Baca sebelum membuat komponen baru (tombol, modal, halaman, cetak PDF, editor, list) agar konsisten dengan pola repo dan tidak reinvent. Mengarahkan ke file docs/ yang relevan.
---

# Indeks Pola UI/Komponen (docs/)

Sebelum membuat komponen baru, cek apakah polanya sudah ada di `docs/`. Ikuti pola yang ada agar konsisten. Baca file docs terkait sebelum implementasi.

| Kebutuhan | Baca file |
|---|---|
| Standar tombol (varian, ukuran, warna, ikon) | `docs/standar-komponen-tombol.md` |
| Standar UI komponen umum | `docs/standar-ui-komponen.md` |
| Halaman bertabel full-height (frame, toolbar sticky, pagination, empty state) | `docs/page-frame-pattern.md` |
| Modal dengan deteksi perubahan (konfirmasi keluar bila dirty) | `docs/dirty-modal-pattern.md` |
| Cetak PDF + tanda tangan (TTD) | `docs/ttd-pattern-pdf-print.md` |
| Editor rich text | `docs/tinymce-editor-pattern.md` |
| List/lookup stabil (decouple dari filter) | `docs/stable-lookup-list-pattern.md` |
| Trait untuk integrasi API eksternal (BPJS/iDRG/Sisrute dll.) | `docs/trait-template-api-eksternal.md` |
| Bridging iDRG (grouper, Stage 1/2, topup) | `docs/idrg-bridging.md` |

## Catatan kunci per pola
- **Page frame / tabel full-height**: yang bikin tabel isi penuh layar = card-level `flex flex-col flex-1 min-h-0` (bukan empty row-nya). Empty state cukup `@forelse`/`@empty` + `<td colspan py-16 text-center>`. JANGAN bikin panel `flex-1` / `@if($this->rows->isEmpty())` sendiri. Acuan: `daftar-rj`. **Gotcha:** wrapper perantara `wire:poll` (`<div ... class="mt-4">`) di atas card WAJIB ikut `flex flex-col flex-1 min-h-0`, kalau tidak card menciut & tabel kosong tampak pendek. **Header tabel list baku:** `text-sm font-semibold tracking-wide text-left text-gray-600 uppercase` (jangan `text-base`/`text-xs`; `font-semibold`, bukan medium/bold).
- **TTD print**: pola `h-16` + `text-center` + `&nbsp;` fallback. HINDARI `display:flex` / `mx-auto` / `<br>` / bracket yang belum di-rebuild.
- **Stable lookup list**: list HANYA depend tanggal; decouple dari filterStatus/filterKlaim.
- **Trait API eksternal**: ikuti pola `VclaimTrait` — event split (mis. `idrg-state-updated` vs `idrg-section-changed`), suffix per-modul.

Lihat juga skill terkait: `blade-safe-edit`, `livewire-input-patterns`.
