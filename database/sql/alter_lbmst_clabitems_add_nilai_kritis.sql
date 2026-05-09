-- ============================================================
-- Alter: LBMST_CLABITEMS — Tambah kolom NILAI_KRITIS
-- Flag Y/N untuk mengaktifkan auto-flagging nilai kritis
-- pada hasil pemeriksaan lab.
--
-- Konvensi nilai (mengikuti pola lowhigh_status):
--   'Y' = item ini punya threshold kritis aktif (sistem flag warning
--         saat hasil melewati ambang batas)
--   'N' / NULL = tidak ada flagging (default)
--
-- Jalankan SEBELUM update_lbmst_clabitems_nilai_kritis.sql
-- (file update set NILAI_KRITIS='Y' untuk item-item tertentu).
-- ============================================================

ALTER TABLE lbmst_clabitems ADD nilai_kritis VARCHAR2(1) DEFAULT 'N';

COMMENT ON COLUMN lbmst_clabitems.nilai_kritis IS 'Flag auto-alert nilai kritis: Y=aktif (sistem munculkan warning bila hasil di luar batas), N/NULL=tidak ada alert';

-- Index untuk filter cepat item bernilai kritis (jumlah biasanya kecil
-- relatif terhadap total item lab)
CREATE INDEX idx_clabitem_nilai_kritis ON lbmst_clabitems (nilai_kritis);
