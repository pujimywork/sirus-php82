-- =============================================================================
-- 2026-05-07 — Tambah kolom KETERANGAN di tabel radiologi & lab luar
-- =============================================================================
-- Radiologi (RJ/UGD/RI): keterangan editable petugas radiologi saat upload.
--   Contoh isi: "AP/lateral", "sebelum kontras", "tampak fraktur radius distal".
-- Lab Luar (lbtxn_checkupoutdtls): keterangan terkait file PDF yang di-upload.
--   Contoh isi: "PCR Covid-19 PRODIA", "BTA — sample 2 dari 3".
-- =============================================================================

ALTER TABLE rstxn_rjrads          ADD (keterangan VARCHAR2(4000));
ALTER TABLE rstxn_ugdrads         ADD (keterangan VARCHAR2(4000));
ALTER TABLE rstxn_riradiologs     ADD (keterangan VARCHAR2(4000));
ALTER TABLE lbtxn_checkupoutdtls  ADD (keterangan VARCHAR2(4000));

COMMIT;

-- Rollback (kalau perlu):
-- ALTER TABLE rstxn_rjrads          DROP COLUMN keterangan;
-- ALTER TABLE rstxn_ugdrads         DROP COLUMN keterangan;
-- ALTER TABLE rstxn_riradiologs     DROP COLUMN keterangan;
-- ALTER TABLE lbtxn_checkupoutdtls  DROP COLUMN keterangan;
