-- =========================================================================
-- DDL: Tambah kolom hasil_bacaan (CLOB) di 3 tabel radiologi
-- Tujuan: simpan teks hasil bacaan dokter radiolog (free-text/rich-text)
--         agar PDF "Hasil Pemeriksaan Radiologi" bisa di-generate ulang
--         kapan saja tanpa hilang sumber datanya.
-- Pola  : sama dengan flow Generate SEP/RM/SKDP — text adalah source of truth,
--         PDF hanya artifact yang disimpan di pdf_path.
-- Catatan: PARKED — eksekusi DBA di lingkungan production. Migrasi Laravel
--         tidak digunakan agar tidak ter-trigger otomatis pada `migrate`.
-- =========================================================================

ALTER TABLE rstxn_rjrads      ADD (hasil_bacaan CLOB);
ALTER TABLE rstxn_ugdrads     ADD (hasil_bacaan CLOB);
ALTER TABLE rstxn_riradiologs ADD (hasil_bacaan CLOB);

COMMENT ON COLUMN rstxn_rjrads.hasil_bacaan      IS 'Teks hasil bacaan radiologi (RJ) — sumber generate PDF';
COMMENT ON COLUMN rstxn_ugdrads.hasil_bacaan     IS 'Teks hasil bacaan radiologi (UGD) — sumber generate PDF';
COMMENT ON COLUMN rstxn_riradiologs.hasil_bacaan IS 'Teks hasil bacaan radiologi (RI) — sumber generate PDF';

COMMIT;
