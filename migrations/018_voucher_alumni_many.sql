SET NAMES utf8mb4;

-- ============================================================
-- Migration 018: Voucher alumni → 1 kode boleh dipakai banyak NISN
--   - Hapus UNIQUE KEY `uk_kode`  (satu kode dipakai banyak NISN)
--   - Hapus UNIQUE KEY `uk_nisn`  (satu NISN boleh terdaftar di banyak kode)
--   - Tambah UNIQUE KEY `uk_kode_nisn` (pasangan kode+NISN tetap unik,
--     tiap pasangan hanya 1x pakai)
--   - Tambah kolom `deskripsi` untuk catatan admin
-- Aman dijalankan ulang (idempotent).
-- ============================================================

-- 1. Hapus unique index `uk_nisn` kalau masih ada
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='voucher_alumni'
      AND INDEX_NAME='uk_nisn'),
  'ALTER TABLE `voucher_alumni` DROP INDEX `uk_nisn`',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Hapus unique index `uk_kode`, ganti index biasa (kalau masih unique)
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='voucher_alumni'
      AND INDEX_NAME='uk_kode' AND NON_UNIQUE = 0),
  'ALTER TABLE `voucher_alumni` DROP INDEX `uk_kode`, ADD INDEX `idx_kode` (`kode`)',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Tambah composite unique (kode, nisn) kalau belum ada
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='voucher_alumni'
      AND INDEX_NAME='uk_kode_nisn'),
  'SELECT 1',
  'ALTER TABLE `voucher_alumni` ADD UNIQUE KEY `uk_kode_nisn` (`kode`, `nisn`)'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Tambah kolom deskripsi (jika belum ada)
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='voucher_alumni'
      AND COLUMN_NAME='deskripsi'),
  'SELECT 1',
  'ALTER TABLE `voucher_alumni` ADD COLUMN `deskripsi` VARCHAR(255) DEFAULT NULL AFTER `nama_siswa`'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;