-- ===============================================================
-- DDL: tambah kolom validasi iDRG ke RSMST_MSTDIAGS + RSMST_MSTPROCEDURES
-- Source: code_system_idrg_20260331.tsv
--
-- Kolom yang ditambah:
--   valid_code : 1 = code valid utk dipakai, 0 = parent/category placeholder
--   accpdx     : 'Y' = boleh jadi diagnosa primer, 'N' = hanya boleh sekunder
--   asterisk   : 1 = code asterisk (harus pair dgn etiologi/dagger code), 0 = tidak
--   im         : 1 = code spesifik iDRG/INACBG Indonesian Modification, 0 = standar WHO
--
-- Default-nya 0/'N' supaya record lama (yg belum di-update lewat file UPDATE) aman
-- diasumsikan invalid sampai di-mark explicit.
-- ===============================================================

ALTER TABLE RSMST_MSTDIAGS ADD (
    valid_code NUMBER(1)   DEFAULT 0,
    accpdx     VARCHAR2(1) DEFAULT 'N',
    asterisk   NUMBER(1)   DEFAULT 0,
    im         NUMBER(1)   DEFAULT 0
);

ALTER TABLE RSMST_MSTPROCEDURES ADD (
    valid_code NUMBER(1)   DEFAULT 0,
    accpdx     VARCHAR2(1) DEFAULT 'N',
    asterisk   NUMBER(1)   DEFAULT 0,
    im         NUMBER(1)   DEFAULT 0
);

COMMIT;
