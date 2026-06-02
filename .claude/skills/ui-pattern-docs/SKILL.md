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
| Halaman dengan frame standar (header/breadcrumb/layout) | `docs/page-frame-pattern.md` |
| Modal dengan deteksi perubahan (konfirmasi keluar bila dirty) | `docs/dirty-modal-pattern.md` |
| Cetak PDF + tanda tangan (TTD) | `docs/ttd-pattern-pdf-print.md` |
| Editor rich text | `docs/tinymce-editor-pattern.md` |
| List/lookup stabil (decouple dari filter) | `docs/stable-lookup-list-pattern.md` |
| Trait untuk integrasi API eksternal (BPJS/iDRG/Sisrute dll.) | `docs/trait-template-api-eksternal.md` |
| Bridging iDRG (grouper, Stage 1/2, topup) | `docs/idrg-bridging.md` |

## Catatan kunci per pola
- **TTD print**: pola `h-16` + `text-center` + `&nbsp;` fallback. HINDARI `display:flex` / `mx-auto` / `<br>` / bracket yang belum di-rebuild.
- **Stable lookup list**: list HANYA depend tanggal; decouple dari filterStatus/filterKlaim.
- **Trait API eksternal**: ikuti pola `VclaimTrait` — event split (mis. `idrg-state-updated` vs `idrg-section-changed`), suffix per-modul.

Lihat juga skill terkait: `blade-safe-edit`, `livewire-input-patterns`.
