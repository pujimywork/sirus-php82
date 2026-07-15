-- ============================================================
-- Alter: RSMST_ROOMS — Tambah kolom ROOM_UUID untuk Satu Sehat
--
-- ROOM_UUID = UUID resource Location SATUSEHAT untuk kamar Rawat Inap.
-- Dipakai sebagai lokasi Encounter RI (class IMP) — analog POLI_UUID di
-- RSMST_POLIS yang dipakai Encounter RJ/UGD. Tipe & panjang SENGAJA
-- disamakan dengan RSMST_POLIS.POLI_UUID (VARCHAR2(100)).
--
-- Cara mengisi: tiap kamar didaftarkan sebagai Location lewat tombol
-- "Daftarkan Location" di /master/kamar (LocationTrait::createLocation,
-- idempoten — search dulu, buat kalau belum ada). UUID hasilnya disimpan
-- di kolom ini. Kamar tanpa ROOM_UUID -> Encounter RI terkirim TANPA
-- location[], dan pindah kamar tak tercatat.
--
-- CATATAN: Oracle akan menolak dengan ORA-01430 kalau kolomnya SUDAH ADA.
-- Cek dulu sebelum menjalankan:
--   SELECT column_name, data_type, data_length
--     FROM user_tab_columns
--    WHERE table_name = 'RSMST_ROOMS' AND column_name = 'ROOM_UUID';
-- ============================================================

ALTER TABLE rsmst_rooms ADD room_uuid VARCHAR2(100);

COMMENT ON COLUMN rsmst_rooms.room_uuid IS 'UUID Location Satu Sehat untuk kamar RI — lokasi Encounter rawat inap (analog rsmst_polis.poli_uuid)';

COMMIT;

-- ============================================================
-- Verifikasi setelah dijalankan
-- ============================================================
-- Struktur kolom:
--   SELECT column_name, data_type, data_length, nullable
--     FROM user_tab_columns
--    WHERE table_name = 'RSMST_ROOMS' AND column_name = 'ROOM_UUID';
--
-- Kamar yang BELUM didaftarkan ke Satu Sehat (perlu klik "Daftarkan Location"):
--   SELECT room_id, room_desc, bangsal_id
--     FROM rsmst_rooms
--    WHERE active_status = '1'
--      AND (room_uuid IS NULL OR LENGTH(TRIM(room_uuid)) = 0);
