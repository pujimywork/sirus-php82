-- ============================================================
-- Alter: LBMST_CLABITEMS — Tambah kolom NILAI_KRITIS
-- Flag boolean untuk mengaktifkan auto-flagging nilai kritis
-- pada hasil pemeriksaan lab.
--
-- Konvensi nilai:
--   1 = item ini punya threshold kritis aktif (sistem flag warning
--       saat hasil melewati ambang batas)
--   0 / NULL = tidak ada flagging (default)
--
-- Jalankan SEBELUM update_lbmst_clabitems_nilai_kritis.sql
-- (file update set NILAI_KRITIS=1 untuk item-item tertentu).
-- ============================================================

ALTER TABLE lbmst_clabitems ADD nilai_kritis NUMBER(1) DEFAULT 0;

COMMENT ON COLUMN lbmst_clabitems.nilai_kritis IS 'Flag auto-alert nilai kritis: 1=aktif (sistem munculkan warning bila hasil di luar batas), 0/NULL=tidak ada alert';

-- Index untuk filter cepat item bernilai kritis (jumlah biasanya kecil
-- relatif terhadap total item lab — bitmap-style index berguna)
CREATE INDEX idx_clabitem_nilai_kritis ON lbmst_clabitems (nilai_kritis);
