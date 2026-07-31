SET NAMES utf8mb4;

-- Tambah kolom tinggi & berat badan calon santri (untuk seragam)

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='tinggi_badan'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran` ADD COLUMN `tinggi_badan` DECIMAL(5,2) DEFAULT NULL AFTER `motivasi`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pendaftaran' AND COLUMN_NAME='berat_badan'),
  'SELECT 1',
  'ALTER TABLE `pendaftaran` ADD COLUMN `berat_badan` DECIMAL(5,2) DEFAULT NULL AFTER `tinggi_badan`'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
