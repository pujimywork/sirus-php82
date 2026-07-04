# Modul Laboratorium (Petugas Lab)

Modul lab berdiri sendiri untuk petugas lab (bukan lab di dalam EMR). Lokasi:
`resources/views/pages/transaksi/penunjang/laborat/`.

## Struktur file

| File | Peran |
|---|---|
| `⚡daftar-laborat.blade.php` | Halaman list/antrian lab (Volt). Embed modal actions, tambah, & sibling etiket. |
| `⚡daftar-laborat-actions.blade.php` | Modal per-pasien: header, tab, footer aksi (proses, simpan hasil, batal, cetak, etiket). |
| `⚡daftar-laborat-tambah-actions.blade.php` | Modal tambah order lab (self-entry). |
| `pemeriksaan-laborat.blade.php` | Tab "Pemeriksaan Laboratorium" — tabel item + input hasil + **Kesimpulan**. |
| `pemeriksaan-luar-laborat.blade.php`, `obat-laborat.blade.php` | Tab pemeriksaan luar & obat/bahan. |
| `display-pasien-laborat/` | Display antrian (layar tunggu). |
| `lab-luar/⚡lab-luar.blade.php` | Lab rujukan luar. |

Cetak/hasil dipakai dari `resources/views/pages/components/rekam-medis/penunjang/laboratorium-display/`
(`laboratorium-display.blade.php` = display layar, `laboratorium-display-print.blade.php` = cetak PDF).

## Status pemeriksaan (`lbtxn_checkuphdrs.checkup_status`)

| Kode | Arti | Tombol utama (footer) |
|---|---|---|
| `P` | Terdaftar (administrasi) | Proses Administrasi · **Batalkan Pendaftaran** |
| `C` | Input hasil | Simpan Hasil Laboratorium |
| `H` | Selesai | Cetak Hasil · **Batalkan Transaksi** |
| `F` | Dibatalkan | — |

Identitas pasien di modal ada di `$headerData` (key `reg_no`, `reg_name`, `sex`, `birth_date`,
`address`, `status_rjri`, `ref_no`, `checkup_status`, `checkup_kesimpulan`). Konteks utama = `checkup_no`.

## Kesimpulan (`checkup_kesimpulan`)

Disimpan di header (`lbtxn_checkuphdrs.checkup_kesimpulan`), tampil **di bawah tabel hasil**
(komponen `pemeriksaan-laborat`), bukan di footer.

- Status `C`: `<x-textarea>` editable, auto-save on blur via `saveKesimpulan()` (dijaga hanya status C).
- Status `H`: read-only (kotak abu-abu, `-` bila kosong).
- Status `P`: disembunyikan (belum ada hasil).
- Simpan = `UPDATE lbtxn_checkuphdrs SET checkup_kesimpulan = ...` untuk `checkup_no` terkait (bukan insert).
- Sudah tampil otomatis di **display layar** (`laboratorium-display.blade.php`) & **cetak PDF**
  (`laboratorium-display-print.blade.php`, pakai `nl2br`), keduanya conditional (hanya bila terisi).

## Batal — dua jenis

Keduanya dijaga role `isAllowedBatal()` = **Admin · Supervisor Penunjang** (operator Lab tidak boleh
batal sendiri). Tombol ada di **zona kiri footer** (terpisah dari tombol utama di kanan).

| Method | Transisi | Efek | Hapus biaya induk? |
|---|---|---|---|
| `batalkanPendaftaran()` | `P → F` | Set status F | Tidak (di P biaya belum di-post) |
| `batalkanTransaksi()` | `H/C → P` | Rollback ke P, reset waktu | Ya (`rstxn_{rj,ugd,ri}labs`) |

**Guard status transaksi induk (WAJIB di kedua method):** tidak boleh batal bila induk
- RJ/UGD: `L` (ditutup/pulang), `F` (dibatalkan), `I` (transfer ke RI)
- RI: `P` (ditutup)

Induk ditentukan `status_rjri` (RJ/UGD/RI) + `ref_no` (→ `rstxn_rjhdrs.rj_no` / `rstxn_ugdhdrs.rj_no` /
`rstxn_rihdrs.rihdr_no`). Semua batal pakai `DB::transaction` + `lockForUpdate` + re-cek status (anti double-submit).

## Akses umum modul (`isAllowedRole()`)

Buka modal, proses administrasi, simpan/cetak hasil = **Admin · Laboratorium**.

## Etiket identitas pasien

Modal lab punya tombol **Etiket** (download PDF) & **Print Etiket** (silent via `sirus-print-agent`
`localhost:9999`, printer bernama `etiket`). Reuse komponen sibling page-level:
`<livewire:pages::components.rekam-medis.etiket.cetak-etiket>` & `...cetak-etiket-auto`
(di-embed di `⚡daftar-laborat.blade.php`). Kontrak event: dispatch `cetak-etiket.open` /
`cetak-etiket-auto.print` dengan named arg `regNo` (= `headerData['reg_no']`). Sistem etiket **bukan**
task-id — per-workstation via local print agent.
