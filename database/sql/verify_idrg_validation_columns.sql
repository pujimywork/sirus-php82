-- ===============================================================
-- Verification: cek hasil UPDATE kolom validasi iDRG
-- Jalankan setelah:
--   1. 2026_05_20_alter_idrg_add_validation_columns.sql
--   2. update_rsmst_mstdiags_idrg_20260331_validation.sql
--   3. update_rsmst_mstprocedures_idrg_20260331_validation.sql
-- ===============================================================

-- Ringkasan distribusi nilai validasi diagnosa
SELECT 'RSMST_MSTDIAGS' AS tabel,
       COUNT(*) AS total,
       SUM(CASE WHEN valid_code = 1 THEN 1 ELSE 0 END) AS valid_codes,
       SUM(CASE WHEN valid_code = 0 THEN 1 ELSE 0 END) AS invalid_codes,
       SUM(CASE WHEN accpdx = 'Y' THEN 1 ELSE 0 END) AS boleh_primer,
       SUM(CASE WHEN accpdx = 'N' THEN 1 ELSE 0 END) AS tidak_boleh_primer,
       SUM(CASE WHEN asterisk = 1 THEN 1 ELSE 0 END) AS asterisk_codes,
       SUM(CASE WHEN im = 1 THEN 1 ELSE 0 END) AS idrg_specific
FROM rsmst_mstdiags;
-- Ekspektasi (dari TSV ICD10_2010_IM):
--   total           : 40815 atau lebih (kalau master punya custom codes)
--   valid_codes     : 36953
--   invalid_codes   :  3862
--   boleh_primer    : 13587
--   tidak_boleh_primer: 27228
--   asterisk_codes  :   852
--   idrg_specific   :  1415

-- Ringkasan distribusi nilai validasi prosedur
SELECT 'RSMST_MSTPROCEDURES' AS tabel,
       COUNT(*) AS total,
       SUM(CASE WHEN valid_code = 1 THEN 1 ELSE 0 END) AS valid_codes,
       SUM(CASE WHEN valid_code = 0 THEN 1 ELSE 0 END) AS invalid_codes,
       SUM(CASE WHEN asterisk = 1 THEN 1 ELSE 0 END) AS asterisk_codes,
       SUM(CASE WHEN im = 1 THEN 1 ELSE 0 END) AS idrg_specific
FROM rsmst_mstprocedures;
-- Ekspektasi (dari TSV ICD9CM_2010_IM):
--   total           : 5475 atau lebih
--   valid_codes     : sekitar 4900-5000
--   invalid_codes   : sekitar 500-600

-- Sample baris diagnosa: campuran valid/invalid/accpdx/asterisk
SELECT diag_id, icdx, diag_desc, valid_code, accpdx, asterisk, im
FROM rsmst_mstdiags
WHERE diag_id IN ('A00','A001','A009','I10','I11','I110','C00','C001','J00','M001')
ORDER BY diag_id;

-- Sample baris prosedur
SELECT proc_id, proc_desc, valid_code, accpdx, asterisk, im
FROM rsmst_mstprocedures
WHERE proc_id IN ('00','00.01','86.1','86.11','86.19','86.2','86.21')
ORDER BY proc_id;

-- Cari diagnosa yang belum ter-update (kemungkinan custom/non-TSV)
SELECT COUNT(*) AS belum_terupdate
FROM rsmst_mstdiags
WHERE valid_code = 0 AND accpdx = 'N' AND asterisk = 0 AND im = 0;
-- Catatan: nilai default kolom = 0/'N', jadi yg 'belum terupdate' campur antara:
--   (a) memang invalid di TSV (valid_code=0)
--   (b) custom code yang tidak ada di TSV (perlu review manual)
-- Untuk membedakan, cross-check pakai script Laravel/PHP atau import TSV ke staging table.
