SET NAMES utf8mb4;

-- ============================================================
-- Migration 026: Wizard portal runtut
--   - tahun_lulus jadi NULLable: kolom ini diisi di step Akademik,
--     bukan saat bikin akun. Sebelumnya NOT NULL DEFAULT tahun
--     berjalan → step Akademik dianggap sudah diisi padahal belum.
--   - Reset backfill 025 yang menyesatkan:
--       * akademik: tahun_lulus default tahun berjalan (isi diam-diam
--         dari INSERT psb) di-reset ke NULL.
--       * jalur: jalur_at yang ter-set hanya karena "reguler default"
--         di-reset ke NULL — reguler pun harus dipilih eksplisit.
-- Rekomendasi: jalankan SETELAH migrasi 025 (script ini guard sendiri,
-- bagian yang butuh kolom 025 dilewati bila kolomnya belum ada).
-- Aman dijalankan ulang (idempotent).
-- ============================================================

-- ── 1. tahun_lulus boleh NULL ────────────────────────────────
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran'
      AND COLUMN_NAME='tahun_lulus' AND IS_NULLABLE='YES'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran` MODIFY COLUMN `tahun_lulus` YEAR NULL DEFAULT NULL COMMENT ''Diisi saat step Akademik di portal (bukan saat daftar akun)'''
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2. Reset tahun_lulus default diam-diam ───────────────────
-- Baris yang TIDAK PERNAH menyimpan step akademik (akademik_at NULL)
-- dan tahun_lulus = tahun berjalan adalah isian default INSERT psb,
-- bukan pilihan user → kosongkan supaya wizard memintanya lagi.
-- (Data lama sebelum sistem wizard sudah di-backfill akademik_at-nya
--  oleh migrasi 025, jadi tidak terkena reset ini.)
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='akademik_at'),
  'UPDATE `pendaftaran` SET `tahun_lulus` = NULL WHERE `akademik_at` IS NULL AND `tahun_lulus` = YEAR(CURDATE())',
  'SELECT 1'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 3. Reset jalur_at reguler-default ────────────────────────
-- jalur_at hanya sah kalau user benar-benar menyimpan step jalur.
-- Baris reguler + tanpa detail + tanpa status = belum memilih → reset.
-- (User yang memang memilih reguler lewat wizard cukup menyimpan
--  ulang sekali; tidak ada data yang hilang.)
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='jalur_at'),
  'UPDATE `pendaftaran` SET `jalur_at` = NULL WHERE `jalur_at` IS NOT NULL AND `jalur` = ''reguler'' AND `jalur_detail` IS NULL AND `jalur_status` = ''none''',
  'SELECT 1'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
