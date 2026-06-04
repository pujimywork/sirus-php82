-- ===============================================================
-- Sync flag validasi (valid_code/accpdx/asterisk/im) antar baris
-- kembar RSMST_MSTDIAGS dengan icdx sama.
--
-- Latar: seed iDRG 20260331 (MERGE by diag_id) menambah baris baru
-- no-dot (mis. K20, M4780), sementara baris legacy Oracle Dev 6i
-- (K20X padding-X / M47.80 dotted) tidak ter-match dan hanya dapat
-- default DDL 0/'N'. Akibatnya 288 kode punya 1 baris hijau + 1
-- baris merah di LOV diagnosa → perilaku pilih kode jadi acak
-- (kasus: K20 terblok di iDRG tapi lolos di SEP).
--
-- Baris legacy TIDAK dihapus karena masih direferensikan >130rb
-- baris transaksi (rstxn_rjdtls/ridtls/ugddtls/rjdtlks/oks).
--
-- Aturan: semua baris dalam grup icdx duplikat diset ke nilai
-- TERBAIK grupnya (MAX) = nilai dari baris seed E-Klaim.
-- Idempotent: aman dijalankan ulang.
-- Ekspektasi: ~576 baris terupdate (288 grup x 2 baris).
-- ===============================================================

UPDATE rsmst_mstdiags m
SET (valid_code, accpdx, asterisk, im) = (
  SELECT MAX(s.valid_code), MAX(s.accpdx), MAX(s.asterisk), MAX(s.im)
  FROM rsmst_mstdiags s WHERE s.icdx = m.icdx
)
WHERE m.icdx IN (SELECT icdx FROM rsmst_mstdiags GROUP BY icdx HAVING COUNT(*) > 1);

COMMIT;

-- Verifikasi: tidak boleh ada lagi grup icdx duplikat dengan flag beda
SELECT COUNT(*) AS grup_masih_beda FROM (
  SELECT icdx FROM rsmst_mstdiags
  GROUP BY icdx HAVING COUNT(*) > 1
     AND (MIN(valid_code) <> MAX(valid_code) OR MIN(accpdx) <> MAX(accpdx))
);
