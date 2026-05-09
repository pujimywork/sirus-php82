-- ============================================================
-- Update: LBMST_CLABITEMS — Set NILAI_KRITIS = 1 untuk item lab
-- yang sudah punya threshold nilai kritis (auto-flag di hasil lab)
--
-- Konteks:
--   Item-item di bawah punya rentang nilai yang melewati ambang
--   batas kritis (mis. Gula Darah Sewaktu 31 mg/dL → hipoglikemia
--   berat). NILAI_KRITIS=1 mengaktifkan flagging otomatis di
--   modul pemeriksaan lab supaya tampil warning kritis.
--
-- Jalankan: sebelum aktifkan fitur "alert nilai kritis" di lab.
-- Idempotent: aman dijalankan ulang (tidak duplikasi).
-- ============================================================

UPDATE lbmst_clabitems
SET nilai_kritis = 1
WHERE clabitem_id IN (
    'GU00014',   -- Gula Darah Puasa (30 mg/dL)
    'GU00015',   -- Gula Darah 2 Jam PP (32 mg/dL)
    'GU00016',   -- Gula Darah Sewaktu (31 mg/dL)
    'KA00164',   -- Kalium (151 mEq/L)
    'CH00166',   -- Klorida (152 mEq/L)
    'CR00029',   -- Kreatinin (29 mg/dL)
    'BI00042',   -- Bilirubin Direk (38 mg/dL)
    'BI00043',   -- Bilirubin Indirek (39 mg/dL)
    'UR00055',   -- Asam Urat (72 mg/dL)
    'CK00154',   -- CK-MB (102 ng/mL)
    'CT00092',   -- Troponin I (87 ng/mL)
    'TS00095',   -- TSH (87 µIU/mL) – rentang 21-54 tahun
    'TS00096',   -- TSH (87 µIU/mL) – rentang 55-87 tahun
    'PC00137',   -- Plateletcrit (27 mL/L) – dari paket HE00001
    'PC500137',  -- Plateletcrit (27 mL/L) – dari paket HE00005
    'AL00046'    -- Albumin (36 mg/dL ≈ 0,36 g/dL)
);

COMMIT;
