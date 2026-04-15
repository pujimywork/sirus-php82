-- ============================================================
-- Alter: LBMST_CLABITEMS — Tambah kolom LOINC untuk Satu Sehat
-- ============================================================

ALTER TABLE lbmst_clabitems ADD loinc_code    VARCHAR2(20);
ALTER TABLE lbmst_clabitems ADD loinc_display VARCHAR2(200);

COMMENT ON COLUMN lbmst_clabitems.loinc_code IS 'Kode LOINC — paket header untuk ServiceRequest/DiagnosticReport, item anak untuk Observation';
COMMENT ON COLUMN lbmst_clabitems.loinc_display IS 'Nama resmi LOINC (contoh: Hemoglobin [Mass/volume] in Blood)';

-- Index untuk lookup by LOINC code
CREATE INDEX idx_clabitem_loinc ON lbmst_clabitems (loinc_code);
