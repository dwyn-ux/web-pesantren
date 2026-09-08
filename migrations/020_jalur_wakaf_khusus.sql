SET NAMES utf8mb4;

-- ============================================================
-- Migration 020: Tarif wakaf khusus per jalur (kaderisasi, alumni)
--   - Kolom wakaf_khusus di jalur_potongan_admin
--   - Alumni: potongan ADM 40% default (juknis), boleh diatur admin
-- Aman dijalankan ulang (idempotent).
-- ============================================================

-- 1. Tambah kolom wakaf_khusus (jika belum ada)
SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jalur_potongan_admin'
      AND COLUMN_NAME='wakaf_khusus'),
  'SELECT 1',
  'ALTER TABLE `jalur_potongan_admin` ADD COLUMN `wakaf_khusus` DECIMAL(15,2) DEFAULT NULL AFTER `spp_p_khusus`'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Default potongan alumni 40% (jika belum ada baris)
INSERT INTO `jalur_potongan_admin` (`jalur`, `potongan_persen`, `wakaf_khusus`)
SELECT 'alumni-sdmua', 40.00, NULL
WHERE NOT EXISTS (SELECT 1 FROM `jalur_potongan_admin` WHERE `jalur`='alumni-sdmua');