SET NAMES utf8mb4;

-- ============================================================
-- Migration 023: Batch cicilan gabungan
--   - pembiayaan_cicilan: + batch_id (satu pembayaran untuk
--     beberapa item tagihan; NULL = cicilan satuan lama)
-- Aman dijalankan ulang (idempotent).
-- ============================================================

SET @ddl = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pembiayaan_cicilan' AND COLUMN_NAME='batch_id'),
  'SELECT 1',
  'ALTER TABLE `pembiayaan_cicilan` ADD COLUMN `batch_id` VARCHAR(32) DEFAULT NULL AFTER `pembiayaan_id`, ADD INDEX `idx_cicilan_batch` (`batch_id`)'
); PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
