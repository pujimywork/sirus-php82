-- =========================================================================
-- DDL: Tambah kolom tgl_bacaan (DATE) di 3 tabel radiologi
-- Tujuan: simpan tanggal/jam saat dokter radiolog membaca & mengesahkan
--         hasil (berbeda dari waktu_entry = waktu order/pemeriksaan).
--         Ditampilkan di cetak "Hasil Pemeriksaan Radiologi" berdampingan
--         dengan "Tgl. Pemeriksaan".
-- Diisi  : otomatis SYSDATE saat "Simpan Bacaan" (upload-radiologi-bacaan).
-- Catatan: PARKED — eksekusi DBA di lingkungan production. Migrasi Laravel
--         tidak digunakan agar tidak ter-trigger otomatis pada `migrate`.
-- =========================================================================

ALTER TABLE rstxn_rjrads      ADD (tgl_bacaan DATE);
ALTER TABLE rstxn_ugdrads     ADD (tgl_bacaan DATE);
ALTER TABLE rstxn_riradiologs ADD (tgl_bacaan DATE);

COMMENT ON COLUMN rstxn_rjrads.tgl_bacaan      IS 'Tanggal/jam bacaan radiolog (RJ) — diisi saat Simpan Bacaan';
COMMENT ON COLUMN rstxn_ugdrads.tgl_bacaan     IS 'Tanggal/jam bacaan radiolog (UGD) — diisi saat Simpan Bacaan';
COMMENT ON COLUMN rstxn_riradiologs.tgl_bacaan IS 'Tanggal/jam bacaan radiolog (RI) — diisi saat Simpan Bacaan';

COMMIT;
