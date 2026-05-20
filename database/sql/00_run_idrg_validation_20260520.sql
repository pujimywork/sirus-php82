-- ===============================================================
-- Runner: deploy kolom validasi iDRG (valid_code/accpdx/asterisk/im)
-- ke RSMST_MSTDIAGS + RSMST_MSTPROCEDURES, lalu import data dari TSV.
--
-- Cara pakai (SQL*Plus / SQL Developer):
--   1. CD ke folder ini: cd /home/avro/Desktop/LARAVEL/sirus-php82/database/sql
--   2. Connect ke Oracle
--   3. Jalankan file ini: @00_run_idrg_validation_20260520.sql
--
-- ATAU paste isi file ini di SQL Developer → "Run Script (F5)".
-- ATAU buka 4 file di bawah satu per satu kalau mau granular.
--
-- Ekspektasi durasi: ~3-5 menit (46k MERGE total, COMMIT per 1000 row)
-- Idempotent: aman dijalankan ulang.
-- ===============================================================

SET DEFINE OFF;
SET ECHO OFF;
SET FEEDBACK OFF;
SET SERVEROUTPUT ON;

PROMPT ============================================
PROMPT  Step 1/4: DDL — tambah kolom validasi
PROMPT ============================================
@@2026_05_20_alter_idrg_add_validation_columns.sql

PROMPT ============================================
PROMPT  Step 2/4: MERGE 40,815 diagnosa ICD10
PROMPT ============================================
@@seed_rsmst_mstdiags_idrg_20260331.sql

PROMPT ============================================
PROMPT  Step 3/4: MERGE 5,475 prosedur ICD9
PROMPT ============================================
@@seed_rsmst_mstprocedures_idrg_20260331.sql

PROMPT ============================================
PROMPT  Step 4/4: Verifikasi hasil
PROMPT ============================================
SET FEEDBACK ON;
@@verify_idrg_validation_columns.sql

PROMPT ============================================
PROMPT  Selesai. Cek angka di output verify.
PROMPT  Ekspektasi diagnosa: 36953 valid / 13587 boleh primer / 852 asterisk / 1415 iDRG
PROMPT ============================================
