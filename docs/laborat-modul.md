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
| `display-pasien-laborat/` | Header identitas pasien di modal (via `MasterPasienTrait`, tema disamakan dgn display RJ/RI/UGD: card unified + 📍📞🆔 no telp/NIK/BPJS). |
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

> **Administrasi RJ/UGD/RI (tab Laboratorium) READ-ONLY** — hanya tampil daftar biaya + total. Order lewat
> EMR, batal/kelola lewat modul penunjang lab (modal `daftar-laborat`). Tidak ada entry/edit/hapus di Administrasi.

## Batal — dua jenis

Keduanya dijaga role `isAllowedBatal()` = **Admin · Supervisor Penunjang**.
**Petugas/staff Laboratorium TIDAK boleh membatalkan sendiri** — batal harus dieskalasi ke **atasan
(Supervisor Penunjang)**. Tombol ada di **zona kiri footer** (terpisah dari tombol utama di kanan).

| Method | Transisi | Efek | Hapus biaya induk? |
|---|---|---|---|
| `batalkanPendaftaran()` | `P → F` | Set status F | Tidak (di P biaya belum di-post) |
| `batalkanTransaksi()` | `H/C → P` | Rollback ke P, reset waktu | Ya (`rstxn_{rj,ugd,ri}labs`) |

**Guard status transaksi induk (WAJIB di kedua method):** tidak boleh batal bila induk
- RJ: `L` (ditutup/pulang), `F` (dibatalkan), `I` (**transfer ke UGD**)
- UGD: `L` (ditutup/pulang), `F` (dibatalkan), `I` (**transfer ke Rawat Inap**)
- RI: `P` (ditutup)

> Catatan kode `I`: di RJ artinya transfer ke **UGD**, di UGD artinya transfer ke **RI** (inap).
> Cocok dengan label badge di Pelayanan RJ ("Transfer UGD") & Pelayanan UGD ("Transfer Inap").

Induk ditentukan `status_rjri` (RJ/UGD/RI) + `ref_no` (→ `rstxn_rjhdrs.rj_no` / `rstxn_ugdhdrs.rj_no` /
`rstxn_rihdrs.rihdr_no`). Semua batal pakai `DB::transaction` + `lockForUpdate` + re-cek status (anti double-submit).

**Audit log:** kedua batal menulis entry ke `userLogs` transaksi induk via `appendAdminLog{RJ,UGD,RI}`
(kategori `MR`) lewat helper `logKeParent()` (di dalam transaksi, lock parent dulu). Butuh `EmrRJ/UGD/RITrait`
di-`use` pada `daftar-laborat-actions` (trait tanpa mount/properti → tak ada collision). Lab **tambah**
(`daftar-laborat-tambah-actions`) juga sudah dilog; proses/simpan hasil belum.

**UX disable:** status induk juga dievaluasi di `evaluasiIndukTerkunci()` (saat `loadHeader`) → set
`$indukTerkunci` + `$indukTerkunciAlasan`. Bila terkunci, tombol batal di-**disable** (`:disabled`) dan
tampil **keterangan alasan** di sebelahnya (+ tooltip). Guard di method tetap ada sebagai defense in depth.

## Akses umum modul (`isAllowedRole()`)

Buka modal, proses administrasi, simpan/cetak hasil = **Admin · Laboratorium**.

## Etiket identitas pasien

Modal lab punya tombol **Etiket** (download PDF) & **Print Etiket** (silent via `sirus-print-agent`
`localhost:9999`, printer bernama `etiket`). Reuse komponen sibling page-level:
`<livewire:pages::components.rekam-medis.etiket.cetak-etiket>` & `...cetak-etiket-auto`
(di-embed di `⚡daftar-laborat.blade.php`). Kontrak event: dispatch `cetak-etiket.open` /
`cetak-etiket-auto.print` dengan named arg `regNo` (= `headerData['reg_no']`). Sistem etiket **bukan**
task-id — per-workstation via local print agent.

## Operasi Data & SQL (gambaran untuk programmer)

> SQL di bawah adalah representasi dari operasi Laravel Query Builder di kode (bukan raw SQL persis).
> Semua nama tabel lab pakai prefix `lbtxn_` (transaksi) & `lbmst_` (master).

### Tabel inti

| Tabel | Peran |
|---|---|
| `lbtxn_checkuphdrs` | Header pemeriksaan. PK `checkup_no`. Kolom penting: `reg_no`, `dr_id`, `emp_id` (pemeriksa), `checkup_status` (P/C/H/F), `status_rjri`, `ref_no`, `checkup_date`, `waktu_masuk_pelayanan`, `waktu_selesai_pelayanan`, `checkup_kesimpulan`, `patient_name`, `klinis_desc`. |
| `lbtxn_checkupdtls` | Detail item pemeriksaan. `checkup_dtl`, `checkup_no`, `clabitem_id`, `lab_item_code`, `lab_result`, `lab_result_status` (H/L/N/R), `price`. |
| `lbtxn_checkupoutdtls` | Pemeriksaan luar. `labout_price`, `labout_result`, `labout_normal`, dst. |
| `lbtxn_checkupobats` | Obat/bahan pakai. `price`, `qty`. |
| `lbmst_clabitems` | Master item lab. `clabitem_desc`, `normal_m/f`, `low/high_limit_m/f`, `unit_convert`, `unit_desc`, `lowhigh_status`, `nilai_kritis`. |
| `lbmst_clabs` | Master kelompok lab. |
| `rstxn_rjlabs` / `rstxn_ugdlabs` / `rstxn_rilabs` | Baris biaya lab yang di-post ke transaksi induk RJ/UGD/RI. |

### SIMPAN — Proses Administrasi (`P → C`)

Hitung total biaya lab lalu **post baris biaya** ke transaksi induk, set status `C` + pemeriksa + waktu masuk.
Semua dalam `DB::transaction` + `lockForUpdate` (re-cek `checkup_status='P'`).

```sql
-- total biaya = pemeriksaan + luar + (obat: price*qty)
SELECT SUM(price)                    FROM lbtxn_checkupdtls    WHERE checkup_no = :cno;  -- $totalCheckup
SELECT SUM(labout_price)             FROM lbtxn_checkupoutdtls WHERE checkup_no = :cno;  -- $totalCheckupOut
SELECT NVL(SUM(NVL(price,0)*NVL(qty,0)),0) FROM lbtxn_checkupobats WHERE checkup_no = :cno; -- $totalBahanAlat

-- post biaya ke induk sesuai status_rjri (contoh RJ; UGD pakai rstxn_ugdlabs + rj_no; RI pakai rstxn_rilabs + rihdr_no + lab_date)
INSERT INTO rstxn_rjlabs (lab_desc, lab_dtl, lab_price, rj_no, checkup_no)
VALUES ('CHECKUP PERTANGGAL ... /NO CHECKUP :cno',
        (SELECT NVL(MAX(lab_dtl)+1,1) FROM rstxn_rjlabs), :totalLabPrice, :refNo, :cno);

-- update header
UPDATE lbtxn_checkuphdrs
SET checkup_status = 'C',
    emp_id = :authEmpId,                 -- hanya kalau emp_id masih kosong
    waktu_masuk_pelayanan = SYSDATE      -- hanya kalau kosong
WHERE checkup_no = :cno;
```

### SIMPAN — Input hasil per item (`updateLabResult`)

Saat status `C`, tiap input hasil (blur) meng-update satu baris detail + hitung `lab_result_status`
(H=Tinggi/L=Rendah/N=Normal/R=Abnormal, dari limit master per jenis kelamin × `unit_convert`).

```sql
UPDATE lbtxn_checkupdtls
SET lab_result = :value, lab_result_status = :status   -- kosongkan keduanya bila value ''
WHERE checkup_no = :cno AND checkup_dtl = :dtl;
```

### SIMPAN — Kesimpulan & Selesai (`C → H`)

```sql
-- Kesimpulan (di bawah tabel hasil, auto-save on blur, hanya status C)
UPDATE lbtxn_checkuphdrs SET checkup_kesimpulan = :value WHERE checkup_no = :cno;

-- Simpan Hasil Laboratorium (C -> H); wajib emp_id sudah terisi
UPDATE lbtxn_checkuphdrs
SET checkup_status = 'H',
    waktu_masuk_pelayanan  = TO_DATE(...checkup_date...),  -- bila kosong
    waktu_selesai_pelayanan = SYSDATE                       -- bila kosong
WHERE checkup_no = :cno;
```

### BATAL

```sql
-- batalkanPendaftaran (P -> F): cek induk dulu (lihat guard), lalu:
UPDATE lbtxn_checkuphdrs
SET checkup_status = 'F', waktu_masuk_pelayanan = NULL, waktu_selesai_pelayanan = NULL
WHERE checkup_no = :cno;

-- batalkanTransaksi (H/C -> P): cek induk dulu, HAPUS biaya dari induk, lalu rollback:
DELETE FROM rstxn_rjlabs  WHERE checkup_no = :cno;   -- / rstxn_ugdlabs / rstxn_rilabs sesuai status_rjri
UPDATE lbtxn_checkuphdrs
SET checkup_status = 'P', waktu_masuk_pelayanan = NULL, waktu_selesai_pelayanan = NULL
WHERE checkup_no = :cno;

-- Guard induk (kedua batal) — batal ditolak bila:
SELECT rj_status FROM rstxn_rjhdrs  WHERE rj_no    = :refNo;  -- RJ:  L/F/I  (I = transfer UGD)
SELECT rj_status FROM rstxn_ugdhdrs WHERE rj_no    = :refNo;  -- UGD: L/F/I  (I = transfer RI)
SELECT ri_status FROM rstxn_rihdrs  WHERE rihdr_no = :refNo;  -- RI:  P (ditutup)
```

### MINDRAY — import hasil dari alat (analyzer)

Tombol **Import Hasil Mindray** (`importMindray()`, hanya status `C`) membaca **koneksi Oracle terpisah**
`oracle_mindray` (config `config/database.php`), lalu tulis hasil ke `lbtxn_checkupdtls` lokal.
`SpecimenID` di Mindray = `checkup_no` kita. Matching item pakai `lab_item_code` = `ItemCode`.

```sql
-- 1) baca dari DB Mindray (connection: oracle_mindray)
SELECT a.PatientName, b.ItemCode, b.Value, b.Low, b.High
FROM tblSpecimenInfo a
JOIN tblTestResult b ON a.SpecimenID = b.SpecimenID
WHERE a.SpecimenID = :cno;

-- 2) simpan patient_name ke header (audit alat)
UPDATE lbtxn_checkuphdrs SET patient_name = :name WHERE checkup_no = :cno;

-- 3) update hasil per item (match lab_item_code = ItemCode). Konversi khusus "alat baru"
--    (Low & High NULL): HGB/MCHC /10, RDW-CV /100, PCT *10.
UPDATE lbtxn_checkupdtls SET lab_result = :labResult
WHERE checkup_no = :cno AND lab_item_code = :itemCode;

-- 4) nol-kan item tertentu (EO00006, BA00007, BA00008)
UPDATE lbtxn_checkupdtls SET lab_result = '0'
WHERE checkup_no = :cno AND clabitem_id IN ('EO00006','BA00007','BA00008');

-- 5) recalculateAllResultStatus(): hitung ulang lab_result_status semua item dari limit master.
```

Semua langkah Mindray dalam satu `DB::transaction`; kegagalan koneksi `oracle_mindray` ditangkap &
ditoast tanpa mengubah data lokal.
